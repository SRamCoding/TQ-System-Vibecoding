<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\IaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Ia\ClienteModeloLenguajeFalso;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\PromptsIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/*
 * El registro de procesos de IA y lo que mueve su autorización (plan 1.11, D1, D2, D5 y D10): la autorización cae si
 * el proceso declara enviar otra cosa o sus datos van a un proveedor externo que Gerencia no autorizó; pasar a uno
 * local no la hace caer, ni un prompt nuevo. El que quedaría enviando fuera sin autorización se apaga en la misma
 * transacción. Procesos, proveedores y modelos inventados.
 */

/**
 * @param  array<string, list<string>>  $camposEnviados
 */
function iaDefinicion(array $camposEnviados = ['party' => ['razon_social']], string $descripcion = 'Propone el nombre comercial.'): DefinicionProcesoIa
{
    return new DefinicionProcesoIa('ejemplo_ia', 'Ejemplo', $descripcion, 'Comercial', ['party'], [], $camposEnviados, [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party']);
}

/** Registra el proceso de ejemplo y lo sincroniza. */
function iaRegistrarEjemplo(?DefinicionProcesoIa $definicion = null): IaProceso
{
    app(RegistroProcesosIa::class)->registrar($definicion ?? iaDefinicion());
    iaServicio()->sincronizar();

    return IaProceso::query()->where('codigo', 'ejemplo_ia')->firstOrFail();
}

function iaServicio(): IaConfiguracionService
{
    return app(IaConfiguracionService::class);
}

/** Un modelo del proveedor con su precio vigente: sin precio no se asigna (ajuste del 2026-10-28). */
function iaModeloDe(IaProveedor $proveedor): IaModelo
{
    return iaConPrecio(IaModelo::factory()->create(['proveedor_id' => $proveedor->id]));
}

function iaConPrecio(IaModelo $modelo): IaModelo
{
    IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15']);

    return $modelo;
}

/**
 * Edita el proveedor cambiando solo lo indicado.
 *
 * @param  array<string, mixed>  $cambios
 */
function iaEditarProveedor(IaProveedor $proveedor, array $cambios): void
{
    $datos = [...$proveedor->refresh()->only(['nombre', 'implementacion', 'es_externo', 'variable_clave', 'llamadas_en_paralelo']), ...$cambios];

    iaServicio()->editarProveedor($proveedor->id, $datos['nombre'], $datos['implementacion'], $datos['es_externo'], $datos['variable_clave'], $datos['llamadas_en_paralelo'], $proveedor->version);
}

/** El proceso con su modelo, autorizado por Gerencia y encendido. */
function iaEncendidoCon(IaProceso $proceso, ?IaModelo $modelo, ?IaModelo $respaldo = null): IaProceso
{
    iaServicio()->asignarModelos($proceso->id, $modelo?->id, $respaldo?->id, $proceso->refresh()->version);
    iaServicio()->autorizar($proceso->id, '0.00', $proceso->refresh()->version);
    $proceso->refresh()->update(['esta_activo' => true]);

    return $proceso->refresh();
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    // Las fábricas usan la implementación `falso`; «otra» es la de la prueba de cambio de implementación.
    app(ClientesModeloLenguaje::class)->registrar('falso', ClienteModeloLenguajeFalso::class);
    app(ClientesModeloLenguaje::class)->registrar('otra', ClienteModeloLenguajeFalso::class);
});

// --- Registro y sincronización (D1) ---

it('crea el proceso declarado apagado, sin modelo, sin autorizar, con tope 0 y sin enviar nada fuera', function () {
    $proceso = iaRegistrarEjemplo();

    expect($proceso->esta_activo)->toBeFalse()
        ->and($proceso->estaAutorizado())->toBeFalse()
        ->and($proceso->tope_mensual_usd)->toBe('0.00')
        ->and($proceso->modelo_id)->toBeNull()
        ->and($proceso->envia_datos_externos)->toBeFalse();
});

it('al sincronizar otra vez no pisa lo configurado', function () {
    $proceso = iaRegistrarEjemplo();
    $proceso->update(['tope_mensual_usd' => '20.00']);

    iaServicio()->sincronizar();

    expect($proceso->refresh()->tope_mensual_usd)->toBe('20.00')
        ->and(IaProceso::query()->count())->toBe(1);
});

it('no registra dos veces el mismo proceso', function () {
    app(RegistroProcesosIa::class)->registrar(iaDefinicion());

    expect(fn () => app(RegistroProcesosIa::class)->registrar(iaDefinicion()))->toThrow(InvalidArgumentException::class, 'ya está registrado');
});

it('rechaza un proceso que enviaría un campo sensible', function () {
    $definicion = new DefinicionProcesoIa('cuentas_ia', 'Cuentas', 'Prueba.', 'Tesorería', ['party_cuenta_bancaria'], [], ['party_cuenta_bancaria' => ['numero_cuenta']], ['banco' => ['type' => 'string']], permisoResolver: 'parties.editar', produce: ['party']);

    expect(fn () => app(RegistroProcesosIa::class)->revisar($definicion))->toThrow(InvalidArgumentException::class, 'es un campo sensible');
});

it('rechaza un origen fuera del morph map', function () {
    $definicion = new DefinicionProcesoIa('raro_ia', 'Raro', 'Prueba.', 'Comercial', ['no_existe'], [], [], ['dato' => ['type' => 'string']], permisoResolver: 'parties.editar', produce: ['party']);

    expect(fn () => app(RegistroProcesosIa::class)->revisar($definicion))->toThrow(InvalidArgumentException::class, 'no está en el morph map');
});

it('rechaza enviar campos de un registro que no es su origen', function () {
    expect(fn () => new DefinicionProcesoIa('mal_ia', 'Mal', 'Prueba.', 'Comercial', ['party'], [], ['user' => ['name']], ['dato' => ['type' => 'string']], permisoResolver: 'parties.editar', produce: ['party']))
        ->toThrow(InvalidArgumentException::class, 'no es uno de sus orígenes');
});

// --- La huella de lo autorizado (D2) ---

it('la huella cambia con lo que sale, no con el orden, la descripción ni lo que propone', function () {
    $base = iaDefinicion(['party' => ['razon_social', 'nombre_comercial']]);
    $mismoOrdenDistinto = iaDefinicion(['party' => ['nombre_comercial', 'razon_social']], 'Otra descripción.');

    expect($mismoOrdenDistinto->huella())->toBe($base->huella())
        ->and(iaDefinicion(['party' => ['razon_social']])->huella())->not->toBe($base->huella());
});

it('si el proceso declara enviar otra cosa, la autorización cae y el proceso se apaga', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    // Otro despliegue: el mismo proceso ahora envía un campo más.
    app()->forgetInstance(RegistroProcesosIa::class);
    iaRegistrarEjemplo(iaDefinicion(['party' => ['razon_social', 'nombre_comercial']]));

    expect($proceso->refresh()->estaAutorizado())->toBeFalse()
        ->and($proceso->esta_activo)->toBeFalse();
});

it('un prompt nuevo no hace caer la autorización', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    // Mismo proceso, mismo envío: solo cambia la versión del prompt, que no es parte de la definición.
    iaServicio()->sincronizar();

    expect($proceso->refresh()->estaAutorizado())->toBeTrue()
        ->and($proceso->esta_activo)->toBeTrue();
});

// --- Los cuatro cambios que mueven el destino ---

it('pasar el modelo a otro proveedor externo hace caer la autorización y apaga el proceso', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->create())->id, null, $proceso->version);

    expect($proceso->refresh()->estaAutorizado())->toBeFalse()
        ->and($proceso->esta_activo)->toBeFalse()
        ->and($proceso->envia_datos_externos)->toBeTrue();
});

it('pasar de un modelo local a uno externo hace caer la autorización y apaga el proceso', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->local()->create()));

    expect($proceso->envia_datos_externos)->toBeFalse();

    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->create())->id, null, $proceso->version);

    expect($proceso->refresh()->estaAutorizado())->toBeFalse()
        ->and($proceso->esta_activo)->toBeFalse();
});

it('pasar de un modelo externo a uno local no hace caer la autorización', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->local()->create())->id, null, $proceso->version);

    expect($proceso->refresh()->estaAutorizado())->toBeTrue()
        ->and($proceso->esta_activo)->toBeTrue()
        ->and($proceso->envia_datos_externos)->toBeFalse();
});

it('volver al proveedor externo que Gerencia ya autorizó no la hace caer', function () {
    $externo = iaModeloDe(IaProveedor::factory()->create());
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), $externo);
    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->local()->create())->id, null, $proceso->version);

    iaServicio()->asignarModelos($proceso->id, $externo->id, null, $proceso->refresh()->version);

    expect($proceso->refresh()->estaAutorizado())->toBeTrue()
        ->and($proceso->esta_activo)->toBeTrue();
});

it('un respaldo en otro proveedor externo también hace caer la autorización', function () {
    $proveedor = IaProveedor::factory()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($proveedor));

    // Respaldo en el mismo proveedor autorizado: sigue.
    iaServicio()->asignarModelos($proceso->id, $proceso->modelo_id, iaModeloDe($proveedor)->id, $proceso->version);
    expect($proceso->refresh()->esta_activo)->toBeTrue();

    iaServicio()->asignarModelos($proceso->id, $proceso->modelo_id, iaModeloDe(IaProveedor::factory()->create())->id, $proceso->version);

    expect($proceso->refresh()->estaAutorizado())->toBeFalse()
        ->and($proceso->esta_activo)->toBeFalse();
});

it('un modelo por defecto externo apaga a los procesos encendidos sin modelo propio que no lo tenían autorizado', function () {
    $local = iaModeloDe(IaProveedor::factory()->local()->create());
    $proceso = iaRegistrarEjemplo();
    iaServicio()->marcarPorDefecto($local->id, $local->refresh()->version);
    $proceso->refresh()->update(['esta_activo' => true]);

    $externo = iaModeloDe(IaProveedor::factory()->create());
    iaServicio()->marcarPorDefecto($externo->id, $externo->refresh()->version);

    expect($proceso->refresh()->esta_activo)->toBeFalse()
        ->and($proceso->envia_datos_externos)->toBeTrue()
        ->and($local->refresh()->es_por_defecto)->toBeFalse()
        ->and($externo->refresh()->es_por_defecto)->toBeTrue();
});

it('el modelo por defecto no toca a los procesos con modelo propio', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));
    $otro = iaModeloDe(IaProveedor::factory()->create());

    iaServicio()->marcarPorDefecto($otro->id, $otro->refresh()->version);

    expect($proceso->refresh()->esta_activo)->toBeTrue()
        ->and($proceso->estaAutorizado())->toBeTrue();
});

it('un proveedor local que pasa a externo apaga a los procesos que lo usan', function () {
    $proveedor = IaProveedor::factory()->local()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($proveedor));

    iaEditarProveedor($proveedor, ['es_externo' => true]);

    expect($proceso->refresh()->esta_activo)->toBeFalse()
        ->and($proceso->estaAutorizado())->toBeFalse()
        ->and($proceso->envia_datos_externos)->toBeTrue();
});

it('un proveedor externo que pasa a local deja de enviar fuera y conserva la autorización', function () {
    $proveedor = IaProveedor::factory()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($proveedor));

    iaEditarProveedor($proveedor, ['es_externo' => false]);

    expect($proceso->refresh()->envia_datos_externos)->toBeFalse()
        ->and($proceso->estaAutorizado())->toBeTrue()
        ->and($proceso->esta_activo)->toBeTrue();
});

it('cambiar la implementación o la variable de la clave de un proveedor externo hace caer la autorización y apaga', function (array $cambios) {
    $proveedor = IaProveedor::factory()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($proveedor));

    iaEditarProveedor($proveedor, $cambios);

    expect($proceso->refresh()->estaAutorizado())->toBeFalse()
        ->and($proceso->esta_activo)->toBeFalse()
        ->and($proceso->destinos()->count())->toBe(0);
})->with([
    'otra implementación' => [['implementacion' => 'otra']],
    'otra variable de clave' => [['variable_clave' => 'IA_CLAVE_OTRA_CUENTA']],
]);

it('cambiar el nombre o el paralelo de un proveedor no toca la autorización', function () {
    $proveedor = IaProveedor::factory()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($proveedor));

    iaEditarProveedor($proveedor, ['nombre' => 'Otro nombre', 'llamadas_en_paralelo' => 5]);

    expect($proceso->refresh()->estaAutorizado())->toBeTrue()
        ->and($proceso->esta_activo)->toBeTrue();
});

it('cambiar la cuenta de un proveedor no toca a los procesos que no lo tienen como destino', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    iaEditarProveedor(IaProveedor::factory()->create(), ['variable_clave' => 'IA_CLAVE_OTRA_CUENTA']);

    expect($proceso->refresh()->estaAutorizado())->toBeTrue();
});

it('rechaza una implementación que el sistema no tiene', function () {
    expect(fn () => iaEditarProveedor(IaProveedor::factory()->create(), ['implementacion' => 'no_existe']))->toThrow(IaRechazada::class, 'no es una implementación');
});

it('rechaza una variable de clave fuera del patrón antes de guardar', function () {
    $proveedor = IaProveedor::factory()->create();

    expect(fn () => iaEditarProveedor($proveedor, ['variable_clave' => 'DB_PASSWORD']))->toThrow(IaRechazada::class, 'IA_CLAVE_')
        ->and($proveedor->refresh()->variable_clave)->not->toBe('DB_PASSWORD');
});

it('la autorización guarda sus destinos externos y los vacía cuando cae', function () {
    $externo = IaProveedor::factory()->create();
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe($externo), iaModeloDe(IaProveedor::factory()->local()->create()));

    expect($proceso->destinos()->pluck('proveedor_id')->all())->toBe([$externo->id]);

    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->create())->id, null, $proceso->version);

    expect($proceso->destinos()->count())->toBe(0);
});

it('autorizar de nuevo reemplaza los destinos', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));
    $otro = IaProveedor::factory()->create();
    iaServicio()->asignarModelos($proceso->id, iaModeloDe($otro)->id, null, $proceso->version);

    iaServicio()->autorizar($proceso->id, '0.00', $proceso->refresh()->version);

    expect($proceso->destinos()->pluck('proveedor_id')->all())->toBe([$otro->id]);
});

it('un modelo no se mueve a otro proveedor', function () {
    $modelo = iaModeloDe(IaProveedor::factory()->create());

    expect(fn () => $modelo->update(['proveedor_id' => IaProveedor::factory()->create()->id]))->toThrow(IaRechazada::class);
});

it('el apagado queda en la bitácora del proceso', function () {
    $proceso = iaEncendidoCon(iaRegistrarEjemplo(), iaModeloDe(IaProveedor::factory()->create()));

    iaServicio()->asignarModelos($proceso->id, iaModeloDe(IaProveedor::factory()->create())->id, null, $proceso->version);

    $ultimo = AuditoriaCambio::query()->where('auditable_type', 'ia_proceso')->where('auditable_id', $proceso->id)->latest('id')->firstOrFail();

    expect($ultimo->valores_nuevos)->toMatchArray(['esta_activo' => false, 'autorizado_por' => null]);
});

it('rechaza un cambio hecho sobre una versión vieja del proceso', function () {
    $proceso = iaRegistrarEjemplo();

    expect(fn () => iaServicio()->asignarModelos($proceso->id, null, null, $proceso->version + 1))
        ->toThrow(VersionDesactualizada::class);
});

// --- Sin precio vigente no hay llamada (ajuste del 2026-10-28) ---

it('no asigna un modelo sin precio vigente: ni principal, ni de respaldo, ni por defecto', function () {
    $proceso = iaRegistrarEjemplo();
    $proveedor = IaProveedor::factory()->create();
    $conPrecio = iaModeloDe($proveedor);
    $sinPrecio = IaModelo::factory()->create(['proveedor_id' => $proveedor->id]);
    // Un precio que empieza mañana todavía no vale.
    IaPrecioModelo::query()->create(['modelo_id' => $sinPrecio->id, 'vigente_desde' => now('America/Lima')->addDay()->toDateString(), 'usd_millon_entrada' => '1', 'usd_millon_salida' => '1']);

    expect(fn () => iaServicio()->asignarModelos($proceso->id, $sinPrecio->id, null, $proceso->refresh()->version))->toThrow(IaRechazada::class, 'no tiene precio vigente')
        ->and(fn () => iaServicio()->asignarModelos($proceso->id, $conPrecio->id, $sinPrecio->id, $proceso->refresh()->version))->toThrow(IaRechazada::class, 'no tiene precio vigente')
        ->and(fn () => iaServicio()->marcarPorDefecto($sinPrecio->id, $sinPrecio->refresh()->version))->toThrow(IaRechazada::class, 'no tiene precio vigente')
        ->and($proceso->refresh()->modelo_id)->toBeNull()
        ->and($sinPrecio->refresh()->es_por_defecto)->toBeFalse();
});

it('un modelo propio se asigna con su precio cargado en 0', function () {
    $proceso = iaRegistrarEjemplo();
    $local = IaModelo::factory()->create(['proveedor_id' => IaProveedor::factory()->create(['es_externo' => false, 'variable_clave' => null])->id]);
    IaPrecioModelo::query()->create(['modelo_id' => $local->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '0', 'usd_millon_salida' => '0']);

    iaServicio()->asignarModelos($proceso->id, $local->id, null, $proceso->refresh()->version);

    expect($proceso->refresh()->modelo_id)->toBe($local->id)
        ->and($proceso->envia_datos_externos)->toBeFalse();
});

// --- Prompts versionados (D10) ---

it('el prompt vigente es el de mayor versión, con su fecha y motivo', function () {
    $prompts = new PromptsIa(base_path('tests/Modules/Nucleo/Fixtures/PromptsIa'));
    $vigente = $prompts->vigente('ejemplo_ia');

    expect($prompts->versiones('ejemplo_ia'))->toBe([1, 2])
        ->and($vigente->version)->toBe(2)
        ->and($vigente->fecha)->toBe('2026-10-29')
        ->and($vigente->motivo)->toBe('Pide la evidencia de cada campo.')
        ->and($vigente->texto)->toStartWith('Propón el nombre comercial');
});

it('rechaza un prompt sin fecha ni motivo, o un proceso sin prompt', function () {
    $prompts = new PromptsIa(base_path('tests/Modules/Nucleo/Fixtures/PromptsIa'));

    expect(fn () => $prompts->vigente('sin_cabecera'))->toThrow(InvalidArgumentException::class, 'fecha y motivo')
        ->and(fn () => $prompts->vigente('no_existe'))->toThrow(InvalidArgumentException::class, 'no tiene prompt');
});

it('todo proceso registrado tiene su prompt con fecha y motivo', function () {
    $prompts = app(PromptsIa::class);

    foreach (app(RegistroProcesosIa::class)->procesos() as $proceso) {
        expect($prompts->versiones($proceso->clave))->toBe(range(1, count($prompts->versiones($proceso->clave))));
        $prompts->vigente($proceso->clave);
    }

    // Hoy ninguno: el mecanismo llega sin procesos (1.11).
    expect(app(RegistroProcesosIa::class)->procesos())->toBe([]);
});
