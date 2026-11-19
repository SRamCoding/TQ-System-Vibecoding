<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Ia\AgregarPrecioModeloIa;
use App\Modules\Nucleo\Acciones\Ia\AutorizarProcesoIa;
use App\Modules\Nucleo\Acciones\Ia\CambiarProcesoIa;
use App\Modules\Nucleo\Acciones\Ia\CrearModeloIa;
use App\Modules\Nucleo\Acciones\Ia\CrearProveedorIa;
use App\Modules\Nucleo\Acciones\Ia\EditarModeloIa;
use App\Modules\Nucleo\Acciones\Ia\EditarProveedorIa;
use App\Modules\Nucleo\Acciones\Ia\MarcarModeloPorDefectoIa;
use App\Modules\Nucleo\Acciones\Ia\ResolverSugerenciaIa;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\AccionSugerenciaIa;
use App\Modules\Nucleo\Enums\EstadoSugerenciaIa;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\IaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Ia\ClienteModeloLenguajeFalso;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\PromptsIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\IaSugerencia;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\IaConfiguracionService;
use App\Modules\Nucleo\Services\IaService;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/*
 * Las acciones de IA (plan 1.11, paso 5), sin interfaz: configurar el catálogo y los procesos (`ia.configurar`, del
 * administrador), autorizar lo que sale (`ia.autorizar`, de Gerencia) y resolver una sugerencia (el permiso de su proceso). Cada
 * rechazo: sistema, inactivo, sin permiso, validación y reglas. Nunca el servicio real. Personas y empresas inventadas.
 */

/** @param array<string, mixed> $datos */
function accIa(string $clave, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.{$clave}", $datos, $usuario);
}

function accAdministrador(): User
{
    return User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
}

function accGerencia(): User
{
    return User::factory()->create()->givePermissionTo('ia.autorizar');
}

function accProceso(): IaProceso
{
    return IaProceso::query()->where('codigo', 'ejemplo_ia')->firstOrFail();
}

/** Un modelo externo con precio y su clave. */
function accModelo(): IaModelo
{
    $proveedor = IaProveedor::factory()->create();
    $modelo = IaModelo::factory()->create(['proveedor_id' => $proveedor->id]);
    IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15']);
    config(['nucleo.ia.claves' => [...config('nucleo.ia.claves', []), $proveedor->variable_clave => 'clave-de-prueba']]);

    return $modelo;
}

/** El proceso con su modelo, autorizado y encendido por las acciones, y una sugerencia por revisar. */
function accSugerencia(): IaSugerencia
{
    $admin = accAdministrador();
    $modelo = accModelo();
    accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => false, 'modelo_id' => $modelo->id, 'version' => accProceso()->version], $admin);
    accIa('ia-autorizar-proceso', ['proceso_id' => accProceso()->id, 'tope_mensual_usd' => '5.00', 'version' => accProceso()->version], accGerencia());
    accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => true, 'modelo_id' => $modelo->id, 'version' => accProceso()->version], $admin);
    app(ClienteModeloLenguajeFalso::class)->responderValida(['campos' => ['nombre_comercial' => [
        'valor' => 'Andina', 'evidencia' => ['pagina' => null, 'texto' => null, 'campo' => 'razon_social'], 'seguridad' => 'alta',
    ]], 'explicacion' => 'De la razón social.']);
    app(IaService::class)->sugerir('ejemplo_ia', Party::factory()->create(['razon_social' => 'Andina del Norte S.A.C.']));

    return IaSugerencia::query()->latest('id')->firstOrFail();
}

/** Quien tiene el permiso que el proceso de ejemplo declara para resolver sus sugerencias. */
function accResolutor(): User
{
    return User::factory()->create()->givePermissionTo('parties.editar');
}

/**
 * Lo que hace la acción de un módulo al usar una sugerencia: crea su registro y la resuelve en la misma transacción,
 * con ese registro como resultado, a nombre de quien ejecuta.
 *
 * @param  array<string, mixed>|null  $correccion
 */
function accModuloUsa(User $persona, IaSugerencia $sugerencia, AccionSugerenciaIa $accion, ?array $correccion = null, ?Closure $crear = null): mixed
{
    return app(ContextoAuditoria::class)->comoUsuario($persona, fn (): mixed => DB::transaction(function () use ($sugerencia, $accion, $correccion, $crear): mixed {
        $registro = $crear !== null ? $crear() : Party::factory()->create(['razon_social' => $correccion['nombre_comercial'] ?? $sugerencia->propuesta['nombre_comercial']['valor']]);

        return app(IaService::class)->resolver($sugerencia->id, $accion, $correccion, $registro, $sugerencia->version);
    }));
}

beforeEach(function () {
    Http::preventStrayRequests();
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    app(ClientesModeloLenguaje::class)->registrar('falso', ClienteModeloLenguajeFalso::class);
    app()->instance(ClienteModeloLenguajeFalso::class, new ClienteModeloLenguajeFalso);
    app()->instance(PromptsIa::class, new PromptsIa(base_path('tests/Modules/Nucleo/Fixtures/PromptsIa')));
    app(RegistroProcesosIa::class)->registrar(new DefinicionProcesoIa('ejemplo_ia', 'Nombre comercial', 'Propone el nombre comercial.', 'Comercial', ['party'], [], ['party' => ['razon_social']], [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party']));
    app(IaConfiguracionService::class)->sincronizar();
});

// --- Registro y permisos ---

it('el núcleo registra las nueve acciones con sus permisos; el administrador configura, pero no autoriza', function () {
    $esperadas = [
        'nucleo.ia-autorizar-proceso' => [AutorizarProcesoIa::class, 'ia.autorizar'],
        'nucleo.ia-cambiar-proceso' => [CambiarProcesoIa::class, 'ia.configurar'],
        'nucleo.ia-crear-proveedor' => [CrearProveedorIa::class, 'ia.configurar'],
        'nucleo.ia-editar-proveedor' => [EditarProveedorIa::class, 'ia.configurar'],
        'nucleo.ia-crear-modelo' => [CrearModeloIa::class, 'ia.configurar'],
        'nucleo.ia-editar-modelo' => [EditarModeloIa::class, 'ia.configurar'],
        'nucleo.ia-marcar-modelo-por-defecto' => [MarcarModeloPorDefectoIa::class, 'ia.configurar'],
        'nucleo.ia-agregar-precio' => [AgregarPrecioModeloIa::class, 'ia.configurar'],
        // Sin permiso fijo: lo revisa el servicio con el que declara el proceso de la sugerencia.
        'nucleo.ia-rechazar-sugerencia' => [ResolverSugerenciaIa::class, null],
    ];
    $admin = Role::findByName(SeguridadService::ROL_ADMINISTRADOR);

    foreach ($esperadas as $clave => [$clase, $permiso]) {
        expect(app(RegistroAcciones::class)->todas())->toHaveKey($clave, $clase)
            ->and(app($clase)->permiso())->toBe($permiso);
    }

    expect($admin->hasPermissionTo('ia.configurar'))->toBeTrue()
        ->and($admin->hasPermissionTo('ia.autorizar'))->toBeFalse();
});

it('rechaza al usuario de sistema, a un inactivo y a quien no tiene el permiso', function (Closure $usuario) {
    expect(fn () => accIa('ia-crear-proveedor', ['codigo' => 'claude', 'nombre' => 'Claude', 'implementacion' => 'falso', 'es_externo' => true, 'llamadas_en_paralelo' => 2], $usuario()))
        ->toThrow(AccionRechazada::class);
})->with([
    'sistema' => [fn () => User::query()->where('es_sistema', true)->sole()],
    'inactivo' => [fn () => User::factory()->inactivo()->create()->givePermissionTo('ia.configurar')],
    'sin permiso' => [fn () => accGerencia()],
]);

// --- Catálogo: proveedores, modelos y precios ---

it('crea y edita un proveedor; guarda el nombre de la variable de la clave, nunca la clave', function () {
    $admin = accAdministrador();
    $id = accIa('ia-crear-proveedor', ['codigo' => 'claude', 'nombre' => 'Claude API', 'implementacion' => 'falso', 'es_externo' => true, 'variable_clave' => 'IA_CLAVE_CLAUDE', 'llamadas_en_paralelo' => 3], $admin);
    $proveedor = IaProveedor::query()->findOrFail($id);

    accIa('ia-editar-proveedor', ['proveedor_id' => $id, 'nombre' => 'Claude', 'implementacion' => 'falso', 'es_externo' => true, 'variable_clave' => 'IA_CLAVE_CLAUDE', 'llamadas_en_paralelo' => 2, 'es_activo' => false, 'version' => $proveedor->version], $admin);

    expect($proveedor->refresh()->only(['nombre', 'variable_clave', 'llamadas_en_paralelo', 'es_activo', 'created_by']))
        ->toBe(['nombre' => 'Claude', 'variable_clave' => 'IA_CLAVE_CLAUDE', 'llamadas_en_paralelo' => 2, 'es_activo' => false, 'created_by' => $admin->id]);
});

it('no crea un proveedor que apunte a otro secreto, con una implementación que no existe o con un código raro', function () {
    $admin = accAdministrador();
    $datos = ['codigo' => 'claude', 'nombre' => 'Claude', 'implementacion' => 'falso', 'es_externo' => true, 'llamadas_en_paralelo' => 2];

    expect(fn () => accIa('ia-crear-proveedor', [...$datos, 'variable_clave' => 'DB_PASSWORD'], $admin))->toThrow(IaRechazada::class, 'IA_CLAVE_')
        ->and(fn () => accIa('ia-crear-proveedor', [...$datos, 'implementacion' => 'otra_api'], $admin))->toThrow(IaRechazada::class, 'no es una implementación')
        ->and(fn () => accIa('ia-crear-proveedor', [...$datos, 'codigo' => 'Claude API'], $admin))->toThrow(ValidationException::class)
        ->and(fn () => accIa('ia-crear-proveedor', [...$datos, 'llamadas_en_paralelo' => 0], $admin))->toThrow(ValidationException::class)
        ->and(IaProveedor::query()->count())->toBe(0);
});

it('crea un modelo, le agrega precio desde hoy y lo marca por defecto; sin precio no se marca', function () {
    $admin = accAdministrador();
    $proveedor = IaProveedor::factory()->create();
    $id = accIa('ia-crear-modelo', ['proveedor_id' => $proveedor->id, 'codigo' => 'claude-opus-5-5', 'nombre' => 'Opus 5.5', 'max_tokens_salida' => 8000], $admin);
    $modelo = IaModelo::query()->findOrFail($id);

    expect(fn () => accIa('ia-marcar-modelo-por-defecto', ['modelo_id' => $id, 'version' => $modelo->version], $admin))->toThrow(IaRechazada::class, 'no tiene precio vigente');

    accIa('ia-agregar-precio', ['modelo_id' => $id, 'vigente_desde' => now('America/Lima')->toDateString(), 'usd_millon_entrada' => '5', 'usd_millon_salida' => '25'], $admin);
    accIa('ia-marcar-modelo-por-defecto', ['modelo_id' => $id, 'version' => $modelo->refresh()->version], $admin);

    expect($modelo->refresh()->es_por_defecto)->toBeTrue()
        ->and($modelo->precios()->sole()->usd_millon_entrada)->toBe('5.000000');
});

it('un precio no empieza en el pasado, no se repite la fecha ni es negativo', function () {
    $admin = accAdministrador();
    $modelo = IaModelo::factory()->create();
    $hoy = now('America/Lima')->toDateString();
    accIa('ia-agregar-precio', ['modelo_id' => $modelo->id, 'vigente_desde' => $hoy, 'usd_millon_entrada' => '1', 'usd_millon_salida' => '1'], $admin);

    expect(fn () => accIa('ia-agregar-precio', ['modelo_id' => $modelo->id, 'vigente_desde' => now('America/Lima')->subDay()->toDateString(), 'usd_millon_entrada' => '1', 'usd_millon_salida' => '1'], $admin))
        ->toThrow(IaRechazada::class, 'no se recalcula')
        ->and(fn () => accIa('ia-agregar-precio', ['modelo_id' => $modelo->id, 'vigente_desde' => $hoy, 'usd_millon_entrada' => '2', 'usd_millon_salida' => '2'], $admin))
        ->toThrow(IaRechazada::class, 'ya tiene un precio')
        ->and(fn () => accIa('ia-agregar-precio', ['modelo_id' => $modelo->id, 'vigente_desde' => now('America/Lima')->addDay()->toDateString(), 'usd_millon_entrada' => '-1', 'usd_millon_salida' => '1'], $admin))
        ->toThrow(ValidationException::class);
});

it('edita un modelo; el modelo por defecto no se desactiva', function () {
    $admin = accAdministrador();
    $modelo = accModelo();
    accIa('ia-marcar-modelo-por-defecto', ['modelo_id' => $modelo->id, 'version' => $modelo->refresh()->version], $admin);

    expect(fn () => accIa('ia-editar-modelo', ['modelo_id' => $modelo->id, 'nombre' => 'Otro', 'max_tokens_salida' => 4000, 'es_activo' => false, 'version' => $modelo->refresh()->version], $admin))
        ->toThrow(IaRechazada::class, 'modelo por defecto');

    accIa('ia-editar-modelo', ['modelo_id' => $modelo->id, 'nombre' => 'Otro', 'max_tokens_salida' => 4000, 'es_activo' => true, 'version' => $modelo->refresh()->version], $admin);

    expect($modelo->refresh()->only(['nombre', 'max_tokens_salida']))->toBe(['nombre' => 'Otro', 'max_tokens_salida' => 4000]);
});

// --- El proceso: configurar, autorizar, encender ---

it('un proceso que envía datos fuera no se enciende sin la autorización de Gerencia; con ella, sí', function () {
    $admin = accAdministrador();
    $modelo = accModelo();

    expect(fn () => accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => true, 'modelo_id' => $modelo->id, 'version' => accProceso()->version], $admin))
        ->toThrow(IaRechazada::class, 'Gerencia tiene que autorizarlo')
        ->and(accProceso()->only(['modelo_id', 'esta_activo']))->toBe(['modelo_id' => null, 'esta_activo' => false]);

    accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => false, 'modelo_id' => $modelo->id, 'version' => accProceso()->version], $admin);
    $gerente = accGerencia();
    accIa('ia-autorizar-proceso', ['proceso_id' => accProceso()->id, 'tope_mensual_usd' => '12.50', 'version' => accProceso()->version], $gerente);
    accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => true, 'modelo_id' => $modelo->id, 'version' => accProceso()->version], $admin);

    expect(accProceso()->only(['esta_activo', 'tope_mensual_usd', 'autorizado_por', 'modelo_id']))
        ->toBe(['esta_activo' => true, 'tope_mensual_usd' => '12.50', 'autorizado_por' => $gerente->id, 'modelo_id' => $modelo->id]);
});

it('el administrador no autoriza ni fija el tope; el tope no es negativo; una versión vieja no se guarda', function () {
    expect(fn () => accIa('ia-autorizar-proceso', ['proceso_id' => accProceso()->id, 'tope_mensual_usd' => '10.00', 'version' => accProceso()->version], accAdministrador()))
        ->toThrow(AccionRechazada::class)
        ->and(fn () => accIa('ia-autorizar-proceso', ['proceso_id' => accProceso()->id, 'tope_mensual_usd' => '-1.00', 'version' => accProceso()->version], accGerencia()))
        ->toThrow(ValidationException::class)
        ->and(fn () => accIa('ia-autorizar-proceso', ['proceso_id' => accProceso()->id, 'tope_mensual_usd' => '1.00', 'version' => accProceso()->version + 1], accGerencia()))
        ->toThrow(VersionDesactualizada::class);
});

it('no asigna un modelo sin precio ni el mismo de respaldo', function () {
    $admin = accAdministrador();
    $modelo = accModelo();
    $sinPrecio = IaModelo::factory()->create();

    expect(fn () => accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => false, 'modelo_id' => $sinPrecio->id, 'version' => accProceso()->version], $admin))
        ->toThrow(IaRechazada::class, 'no tiene precio vigente')
        ->and(fn () => accIa('ia-cambiar-proceso', ['proceso_id' => accProceso()->id, 'esta_activo' => false, 'modelo_id' => $modelo->id, 'modelo_respaldo_id' => $modelo->id, 'version' => accProceso()->version], $admin))
        ->toThrow(IaRechazada::class, 'otro que el principal');
});

// --- Resolver una sugerencia (D9): la resuelve el módulo; rechazarla, el núcleo ---

it('la acción del módulo crea su registro y acepta la sugerencia en la misma transacción, con ese registro como resultado', function () {
    $sugerencia = accSugerencia();
    $persona = accResolutor();

    accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Aceptada);
    $empresa = Party::query()->latest('id')->firstOrFail();

    expect($empresa->razon_social)->toBe('Andina')
        ->and($sugerencia->refresh()->only(['estado', 'accion', 'resuelta_por', 'resultado_type', 'resultado_id', 'correccion']))
        ->toBe(['estado' => EstadoSugerenciaIa::Resuelta, 'accion' => AccionSugerenciaIa::Aceptada, 'resuelta_por' => $persona->id, 'resultado_type' => 'party', 'resultado_id' => $empresa->id, 'correccion' => null]);
});

it('al corregirla guarda los campos cambiados, cada uno del tipo que pide el proceso', function () {
    $sugerencia = accSugerencia();
    $persona = accResolutor();

    expect(fn () => accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Corregida, ['telefono' => '999']))->toThrow(IaRechazada::class, 'no es un campo de la sugerencia')
        ->and(fn () => accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Corregida, ['nombre_comercial' => 7]))->toThrow(IaRechazada::class, 'no es del tipo')
        ->and(fn () => accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Corregida))->toThrow(IaRechazada::class, 'falta lo que se cambió')
        ->and(fn () => accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Aceptada, ['nombre_comercial' => 'X']))->toThrow(IaRechazada::class, 'solo se corrige');

    accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Corregida, ['nombre_comercial' => 'Andina Norte']);

    expect($sugerencia->refresh()->accion)->toBe(AccionSugerenciaIa::Corregida)
        ->and($sugerencia->correccion)->toBe(['nombre_comercial' => 'Andina Norte'])
        ->and(Party::query()->find($sugerencia->resultado_id)?->razon_social)->toBe('Andina Norte');
});

it('el resultado es del tipo que el proceso declara producir; cualquier otro se rechaza y no se guarda nada', function () {
    $sugerencia = accSugerencia();
    $personas = User::query()->count();

    expect(fn () => accModuloUsa(accResolutor(), $sugerencia, AccionSugerenciaIa::Aceptada, crear: fn (): User => User::factory()->create()))
        ->toThrow(IaRechazada::class, 'no produce «user»')
        ->and($sugerencia->refresh()->estado)->toBe(EstadoSugerenciaIa::PorRevisar)
        // El registro del módulo se deshace con la transacción.
        ->and(User::query()->count())->toBe($personas + 1);
});

it('aceptarla o corregirla exige el registro creado; rechazarla no lleva ninguno', function () {
    $sugerencia = accSugerencia();
    $persona = accResolutor();
    $resolver = fn (AccionSugerenciaIa $accion, ?Party $resultado): IaSugerencia => app(ContextoAuditoria::class)->comoUsuario($persona,
        fn (): IaSugerencia => DB::transaction(fn (): IaSugerencia => app(IaService::class)->resolver($sugerencia->id, $accion, null, $resultado, $sugerencia->version)));

    expect(fn () => $resolver(AccionSugerenciaIa::Aceptada, null))->toThrow(IaRechazada::class, 'va el registro que se creó')
        ->and(fn () => $resolver(AccionSugerenciaIa::Rechazada, Party::factory()->create()))->toThrow(IaRechazada::class, 'rechazada no creó nada');
});

it('sin el permiso que declara el proceso no se resuelve, aunque tenga otros', function () {
    $sugerencia = accSugerencia();
    $otra = User::factory()->create()->givePermissionTo('ia.configurar');

    expect(fn () => accModuloUsa($otra, $sugerencia, AccionSugerenciaIa::Aceptada))->toThrow(IaRechazada::class, 'No tienes el permiso')
        ->and(fn () => accIa('ia-rechazar-sugerencia', ['sugerencia_id' => $sugerencia->id, 'version' => $sugerencia->version], $otra))->toThrow(IaRechazada::class, 'No tienes el permiso')
        ->and($sugerencia->refresh()->estado)->toBe(EstadoSugerenciaIa::PorRevisar);
});

it('rechazar es la acción del núcleo, con el permiso del proceso; una resuelta no se resuelve otra vez', function () {
    $sugerencia = accSugerencia();
    $persona = accResolutor();
    $datos = ['sugerencia_id' => $sugerencia->id, 'version' => $sugerencia->version];

    expect(fn () => accIa('ia-rechazar-sugerencia', $datos, User::query()->where('es_sistema', true)->sole()))->toThrow(AccionRechazada::class);

    accIa('ia-rechazar-sugerencia', $datos, $persona);

    expect($sugerencia->refresh()->only(['estado', 'accion', 'resuelta_por', 'resultado_type']))
        ->toBe(['estado' => EstadoSugerenciaIa::Resuelta, 'accion' => AccionSugerenciaIa::Rechazada, 'resuelta_por' => $persona->id, 'resultado_type' => null])
        ->and(fn () => accIa('ia-rechazar-sugerencia', [...$datos, 'version' => $sugerencia->version], $persona))->toThrow(IaRechazada::class, 'ya se resolvió')
        ->and(fn () => accModuloUsa($persona, $sugerencia, AccionSugerenciaIa::Aceptada))->toThrow(IaRechazada::class, 'ya se resolvió')
        ->and(fn () => accIa('ia-rechazar-sugerencia', $datos, $persona))->toThrow(VersionDesactualizada::class);
});
