<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use App\Modules\Nucleo\Models\IaLlamada;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Cada inserción va en su propia transacción (un savepoint): la que falla no aborta la de la prueba.
 *
 * La base protege el mecanismo de IA aunque se salte el servicio (regla 5; plan 1.11, D2 a D5 y D9): la clave solo
 * desde una variable `IA_CLAVE_…`, nada sale sin autorización, un solo modelo por defecto, el precio y el origen
 * consistentes con su llamada. Las filas que se ponen a prueba se escriben por query builder.
 */

function iaSistema(): int
{
    return app(UsuarioSistema::class)->id();
}

/**
 * @param  array<string, mixed>  $cambios
 */
function iaProcesoCrudo(array $cambios = []): int
{
    return DB::transaction(fn (): int => DB::table('ia_procesos')->insertGetId(['codigo' => fake()->unique()->lexify('proceso_????'), 'created_by' => iaSistema(), ...$cambios]));
}

/**
 * @param  array<string, mixed>  $cambios
 */
function iaLlamadaCruda(int $proceso, IaModelo $modelo, int $origen, array $cambios = []): int
{
    // Toda llamada lleva el precio de su modelo (ajuste del 2026-10-28): uno de 0 si la prueba no pone otro.
    $precio = array_key_exists('precio_id', $cambios) ? null : IaPrecioModelo::query()->firstOrCreate(
        ['modelo_id' => $modelo->id, 'vigente_desde' => '2026-01-01'],
        ['usd_millon_entrada' => '0', 'usd_millon_salida' => '0'],
    )->id;

    return DB::transaction(fn (): int => DB::table('ia_llamadas')->insertGetId([
        'proceso_id' => $proceso, 'origen_type' => 'party', 'origen_id' => $origen, 'modelo_id' => $modelo->id, 'precio_id' => $precio,
        'prompt_version' => 1, 'resultado' => ResultadoLlamadaIa::Valida->value, 'duracion_ms' => 900,
        'created_by' => iaSistema(), ...$cambios,
    ]));
}

/**
 * @param  array<string, mixed>  $cambios
 */
function iaSugerenciaCruda(int $proceso, int $llamada, int $origen, array $cambios = []): int
{
    return DB::transaction(fn (): int => DB::table('ia_sugerencias')->insertGetId([
        'numero' => fake()->unique()->numerify('SUG-2026-######'), 'proceso_id' => $proceso, 'llamada_id' => $llamada,
        'origen_type' => 'party', 'origen_id' => $origen, 'propuesta' => '{}', 'seguridad_por_campo' => '{}',
        'explicacion' => 'Lo dice la primera página.', 'created_by' => iaSistema(), ...$cambios,
    ]));
}

// --- Proveedores y modelos (D5) ---

it('solo acepta la clave desde una variable IA_CLAVE_…, nunca otro secreto', function (string $variable) {
    expect(fn () => IaProveedor::factory()->create(['variable_clave' => $variable]))
        ->toThrow(QueryException::class, 'ia_proveedores_variable_clave_check');
})->with(['DB_PASSWORD', 'APP_KEY', 'ia_clave_x', 'IA_CLAVE_', 'IA_CLAVE_A-B', 'X_IA_CLAVE_A']);

it('acepta una variable con el patrón, o ninguna en un proveedor local', function () {
    expect(IaProveedor::factory()->create(['variable_clave' => 'IA_CLAVE_CLAUDE_2'])->exists)->toBeTrue()
        ->and(IaProveedor::factory()->local()->create()->variable_clave)->toBeNull();
});

it('tiene un solo modelo por defecto, y activo', function () {
    IaModelo::factory()->create(['es_por_defecto' => true]);

    expect(fn () => IaModelo::factory()->create(['es_por_defecto' => true]))->toThrow(QueryException::class, 'ia_modelos_un_por_defecto_unique')
        ->and(fn () => IaModelo::factory()->create(['es_por_defecto' => false, 'es_activo' => false])->update(['es_por_defecto' => true]))
        ->toThrow(QueryException::class, 'ia_modelos_por_defecto_activo_check');
});

// --- Procesos (D2, D3) ---

it('no enciende sin autorización un proceso que envía datos fuera', function () {
    expect(fn () => iaProcesoCrudo(['esta_activo' => true, 'envia_datos_externos' => true]))
        ->toThrow(QueryException::class, 'ia_procesos_activo_autorizado_check');
});

it('enciende sin autorización un proceso que no saca datos de la empresa, y uno autorizado', function () {
    $local = iaProcesoCrudo(['esta_activo' => true, 'envia_datos_externos' => false]);
    $autorizado = iaProcesoCrudo([
        'esta_activo' => true, 'autorizado_por' => iaSistema(), 'autorizado_at' => now(), 'autorizacion_huella' => str_repeat('a', 64),
    ]);

    expect(IaProceso::query()->whereKey([$local, $autorizado])->where('esta_activo', true)->count())->toBe(2);
});

it('guarda la autorización completa: quién, cuándo y qué autorizó', function () {
    expect(fn () => iaProcesoCrudo(['autorizado_por' => iaSistema(), 'autorizado_at' => now()]))
        ->toThrow(QueryException::class, 'ia_procesos_autorizacion_check');
});

it('un destino autorizado existe solo mientras dura la autorización, con su huella', function () {
    $proveedor = IaProveedor::factory()->create();
    $huella = str_repeat('a', 64);
    $sinAutorizar = iaProcesoCrudo();
    $autorizado = iaProcesoCrudo(['autorizado_por' => iaSistema(), 'autorizado_at' => now(), 'autorizacion_huella' => $huella]);
    $destino = fn (int $proceso, string $conHuella, ?int $proveedorId = null): int => DB::transaction(fn (): int => DB::table('ia_proceso_destinos')->insertGetId([
        'proceso_id' => $proceso, 'proveedor_id' => $proveedorId ?? $proveedor->id, 'autorizacion_huella' => $conHuella, 'created_by' => iaSistema(),
    ]));

    expect(fn () => $destino($sinAutorizar, $huella))->toThrow(QueryException::class, 'ia_proceso_destinos_autorizacion_foreign')
        ->and(fn () => $destino($autorizado, str_repeat('b', 64)))->toThrow(QueryException::class, 'ia_proceso_destinos_autorizacion_foreign')
        ->and(fn () => $destino($autorizado, $huella, 999999))->toThrow(QueryException::class, 'ia_proceso_destinos_proveedor_id_foreign');

    $destino($autorizado, $huella);

    expect(fn () => $destino($autorizado, $huella))->toThrow(QueryException::class, 'ia_proceso_destinos_proceso_id_proveedor_id_unique')
        ->and(fn () => DB::transaction(fn () => DB::table('ia_procesos')->where('id', $autorizado)->update(['autorizado_por' => null, 'autorizado_at' => null, 'autorizacion_huella' => null])))
        ->toThrow(QueryException::class, 'ia_proceso_destinos_autorizacion_foreign');
});

it('nace apagado, sin autorización y con tope 0', function () {
    $proceso = IaProceso::query()->findOrFail(iaProcesoCrudo());

    expect($proceso->esta_activo)->toBeFalse()
        ->and($proceso->estaAutorizado())->toBeFalse()
        ->and($proceso->tope_mensual_usd)->toBe('0.00')
        ->and(fn () => iaProcesoCrudo(['tope_mensual_usd' => '-1']))->toThrow(QueryException::class, 'ia_procesos_tope_check');
});

it('no usa el mismo modelo como respaldo de sí mismo', function () {
    $modelo = IaModelo::factory()->create();

    expect(fn () => iaProcesoCrudo(['modelo_id' => $modelo->id, 'modelo_respaldo_id' => $modelo->id]))
        ->toThrow(QueryException::class, 'ia_procesos_respaldo_check');
});

// --- Llamadas (D3, D4) ---

it('distingue la respuesta cortada de la mal formada', function () {
    $modelo = IaModelo::factory()->create();
    $proceso = iaProcesoCrudo();
    $origen = Party::factory()->create()->id;

    iaLlamadaCruda($proceso, $modelo, $origen, ['resultado' => 'cortada']);
    iaLlamadaCruda($proceso, $modelo, $origen, ['resultado' => 'invalida']);

    expect(IaLlamada::query()->pluck('resultado')->all())->toEqualCanonicalizing([ResultadoLlamadaIa::Cortada, ResultadoLlamadaIa::Invalida])
        ->and(fn () => iaLlamadaCruda($proceso, $modelo, $origen, ['resultado' => 'rara']))->toThrow(QueryException::class, 'ia_llamadas_resultado_check');
});

it('rechaza un origen que no es una clave del morph map', function () {
    expect(fn () => iaLlamadaCruda(iaProcesoCrudo(), IaModelo::factory()->create(), 1, ['origen_type' => Party::class]))
        ->toThrow(QueryException::class, 'ia_llamadas_origen_type_check');
});

it('aplica solo un precio del modelo llamado, y sin precio no hay llamada', function () {
    $modelo = IaModelo::factory()->create();
    $otro = IaModelo::factory()->create();
    $precioDelOtro = IaPrecioModelo::query()->create(['modelo_id' => $otro->id, 'vigente_desde' => '2026-10-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15']);
    $proceso = iaProcesoCrudo();
    $origen = Party::factory()->create()->id;

    expect(fn () => iaLlamadaCruda($proceso, $modelo, $origen, ['precio_id' => $precioDelOtro->id, 'costo_usd' => '0.01']))
        ->toThrow(QueryException::class, 'ia_llamadas_precio_foreign')
        ->and(fn () => iaLlamadaCruda($proceso, $modelo, $origen, ['precio_id' => null]))
        ->toThrow(QueryException::class, 'precio_id');
});

it('no repite el precio de un modelo para la misma fecha', function () {
    $modelo = IaModelo::factory()->create();
    $precio = ['modelo_id' => $modelo->id, 'vigente_desde' => '2026-10-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15'];
    IaPrecioModelo::query()->create($precio);

    expect(fn () => IaPrecioModelo::query()->create($precio))->toThrow(QueryException::class, 'ia_precios_modelo_modelo_id_vigente_desde_unique');
});

// --- Sugerencias (D4, D9) ---

it('la sugerencia es del mismo proceso y origen que su llamada, y una sola por llamada', function () {
    $modelo = IaModelo::factory()->create();
    $proceso = iaProcesoCrudo();
    $origen = Party::factory()->create()->id;
    $otroOrigen = Party::factory()->create()->id;
    $llamada = iaLlamadaCruda($proceso, $modelo, $origen);

    expect(fn () => iaSugerenciaCruda($proceso, $llamada, $otroOrigen))->toThrow(QueryException::class, 'ia_sugerencias_llamada_foreign')
        ->and(fn () => iaSugerenciaCruda(iaProcesoCrudo(), $llamada, $origen))->toThrow(QueryException::class, 'ia_sugerencias_llamada_foreign');

    iaSugerenciaCruda($proceso, $llamada, $origen);

    expect(fn () => iaSugerenciaCruda($proceso, $llamada, $origen))->toThrow(QueryException::class, 'ia_sugerencias_llamada_id_unique');
});

it('una sugerencia resuelta dice qué hizo la persona, quién y cuándo', function (array $cambios, string $restriccion) {
    $modelo = IaModelo::factory()->create();
    $proceso = iaProcesoCrudo();
    $origen = Party::factory()->create()->id;
    $cambios = array_map(fn (mixed $valor): mixed => $valor === 'sistema' ? iaSistema() : $valor, $cambios);

    expect(fn () => iaSugerenciaCruda($proceso, iaLlamadaCruda($proceso, $modelo, $origen), $origen, $cambios))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'resuelta sin acción' => [['estado' => 'resuelta'], 'ia_sugerencias_resuelta_check'],
    'acción sin resolver' => [['accion' => 'aceptada'], 'ia_sugerencias_resuelta_check'],
    'resuelta sin quién' => [['estado' => 'resuelta', 'accion' => 'aceptada', 'resuelta_at' => '2026-10-28 10:00'], 'ia_sugerencias_resuelta_check'],
    'corregida sin corrección' => [['estado' => 'resuelta', 'accion' => 'corregida', 'resuelta_por' => 'sistema', 'resuelta_at' => '2026-10-28 10:00'], 'ia_sugerencias_correccion_check'],
    'corrección sin corregir' => [['correccion' => '{}'], 'ia_sugerencias_correccion_check'],
    'rechazada con resultado' => [['estado' => 'resuelta', 'accion' => 'rechazada', 'resuelta_por' => 'sistema', 'resuelta_at' => '2026-10-28 10:00', 'resultado_type' => 'party', 'resultado_id' => 1], 'ia_sugerencias_resultado_check'],
    'resultado sin resolver' => [['resultado_type' => 'party', 'resultado_id' => 1], 'ia_sugerencias_resultado_check'],
    // Revisión de arquitectura: al aceptarla o corregirla, el registro creado va siempre.
    'aceptada sin el registro creado' => [['estado' => 'resuelta', 'accion' => 'aceptada', 'resuelta_por' => 'sistema', 'resuelta_at' => '2026-10-28 10:00'], 'ia_sugerencias_resultado_check'],
]);
