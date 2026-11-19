<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\CentroDeCosto;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Imputacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;

/*
 * M11 · Imputación (plan 1.9; regla 3), con una línea de costo inventada (`ModeloAuditado`): todavía ningún documento
 * con costo existe (etapa 2). Hoy el destino es un centro de costo; los cinco del v4 vienen sembrados.
 */

beforeEach(function () {
    test()->seed([ParametrosSeeder::class, CatalogosSeeder::class]);
    ModeloAuditado::crearTabla();
    test()->actingAs(User::factory()->create());
});

function imLinea(): ModeloAuditado
{
    return ModeloAuditado::query()->create(['nombre' => 'Línea de costo '.fake()->unique()->numerify('###')]);
}

function imDestinar(ModeloAuditado $linea, string $centro = 'ADM', string $moneda = 'PEN', string $fecha = '2026-10-26'): Imputacion
{
    return app(ImputacionService::class)->destinar($linea, CentroDeCosto::porCodigo($centro), CarbonImmutable::parse($fecha), $moneda);
}

function imMover(ModeloAuditado $linea, MomentoImputacion $momento, string $diferencia): Imputacion
{
    return app(ImputacionService::class)->mover($linea, $momento, $diferencia);
}

test('vienen los cinco centros de costo del v4, por confirmar con Contabilidad', function () {
    expect(CentroCosto::query()->orderBy('codigo')->pluck('nombre', 'codigo')->all())
        ->toBe(['ADM' => 'Administración', 'COM' => 'Comercial', 'OPE' => 'Operaciones', 'RRHH' => 'Recursos humanos', 'SUM' => 'Suministro']);
});

test('una línea sin destino no mueve montos', function () {
    expect(fn () => imMover(imLinea(), MomentoImputacion::Comprometido, '100'))->toThrow(ImputacionRechazada::class, 'todavía no tiene a dónde va');
});

test('escenario: comprometer 1 000, ejecutar 600, ejecutar 500 más se rechaza; anular resta y deja cero', function () {
    $linea = imLinea();
    imDestinar($linea);

    imMover($linea, MomentoImputacion::Comprometido, '1000');
    imMover($linea, MomentoImputacion::Ejecutado, '600');

    expect(fn () => imMover($linea, MomentoImputacion::Ejecutado, '500'))->toThrow(ImputacionRechazada::class, 'pasaría lo comprometido')
        ->and(fn () => imMover($linea, MomentoImputacion::Comprometido, '-500'))->toThrow(ImputacionRechazada::class, 'pasaría lo comprometido');

    imMover($linea, MomentoImputacion::Pagado, '600');
    imMover($linea, MomentoImputacion::Pagado, '-600');
    imMover($linea, MomentoImputacion::Ejecutado, '-600');
    $imputacion = imMover($linea, MomentoImputacion::Comprometido, '-1000');

    expect([$imputacion->monto_comprometido, $imputacion->monto_ejecutado, $imputacion->monto_pagado])->toBe(['0.00', '0.00', '0.00'])
        ->and(fn () => imMover($linea, MomentoImputacion::Pagado, '-1'))->toThrow(ImputacionRechazada::class, 'negativo');
});

test('una línea tiene un solo destino; se cambia mientras no tenga montos, después no', function () {
    $linea = imLinea();
    imDestinar($linea, 'ADM');
    $cambiada = imDestinar($linea, 'OPE');

    expect(Imputacion::query()->count())->toBe(1)
        ->and($cambiada->centroCosto->codigo)->toBe('OPE');

    imMover($linea, MomentoImputacion::Comprometido, '10');
    expect(fn () => imDestinar($linea, 'COM'))->toThrow(ImputacionRechazada::class, 'ya tiene montos');
});

test('un centro desactivado o que no existe no es destino', function () {
    CentroCosto::query()->where('codigo', 'COM')->update(['es_activo' => false]);

    expect(fn () => imDestinar(imLinea(), 'COM'))->toThrow(ImputacionRechazada::class, 'desactivado')
        ->and(fn () => imDestinar(imLinea(), 'XYZ'))->toThrow(ImputacionRechazada::class, 'No existe');
});

test('un monto mal escrito se rechaza', function (string $diferencia) {
    $linea = imLinea();
    imDestinar($linea);

    expect(fn () => imMover($linea, MomentoImputacion::Comprometido, $diferencia))->toThrow(ImputacionRechazada::class, 'hasta dos decimales');
})->with(['con tres decimales' => '1.005', 'con letras' => 'mil', 'vacío' => '']);

test('la base exige un destino, montos no negativos y ejecutado no mayor que comprometido', function (array $cambio) {
    $linea = imLinea();
    $imputacion = imDestinar($linea);

    expect(fn () => DB::transaction(fn () => DB::table('imputaciones')->where('id', $imputacion->id)->update($cambio)))->toThrow(QueryException::class);
})->with([
    'sin destino' => [['centro_costo_id' => null]],
    'negativo' => [['monto_pagado' => -1]],
    'ejecutado mayor' => [['monto_ejecutado' => 5]],
    'tipo de cambio cero' => [['tipo_cambio' => 0]],
]);

test('totales por centro de costo, en soles con el tipo de cambio de cada línea, y por rango de fechas', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-10-26'), '3.7000', '3.8000', OrigenTipoCambio::Manual);
    $soles = imLinea();
    $dolares = imLinea();
    $otroMes = imLinea();
    imDestinar($soles, 'ADM');
    imDestinar($dolares, 'ADM', 'USD');
    imDestinar($otroMes, 'OPE', fecha: '2026-09-15');
    imMover($soles, MomentoImputacion::Comprometido, '1000');
    imMover($dolares, MomentoImputacion::Comprometido, '100');
    imMover($dolares, MomentoImputacion::Ejecutado, '50');
    imMover($otroMes, MomentoImputacion::Comprometido, '200');

    $totales = collect(app(ImputacionService::class)->totalesPorCentroDeCosto())->keyBy(fn (array $t): string => $t['centro']->codigo);
    $octubre = collect(app(ImputacionService::class)->totalesPorCentroDeCosto(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')));

    expect($totales['ADM'])->toMatchArray(['comprometido' => '1380.00', 'ejecutado' => '190.00', 'pagado' => '0.00'])
        ->and($totales['OPE']['comprometido'])->toBe('200.00')
        ->and($octubre->map(fn (array $t): string => $t['centro']->codigo)->all())->toBe(['ADM'])
        ->and(Imputacion::query()->where('origen_id', $dolares->id)->value('moneda_id'))->toBe(app(CatalogosService::class)->idPorCodigo(Catalogo::Monedas, 'USD'));
});

test('destinar una línea que ya tiene imputación no choca con la unicidad: sigue como cambio', function () {
    $linea = imLinea();
    imDestinar($linea, 'ADM');

    // Como si otro proceso la hubiera creado entre medio: la base decide y el servicio sigue como cambio.
    $segunda = imDestinar($linea, 'SUM');

    expect(Imputacion::query()->count())->toBe(1)
        ->and($segunda->centroCosto->codigo)->toBe('SUM');
});

test('un monto que pasa el máximo de la columna se rechaza con un mensaje claro', function () {
    $linea = imLinea();
    imDestinar($linea);
    imMover($linea, MomentoImputacion::Comprometido, '999999999999');

    expect(fn () => imMover($linea, MomentoImputacion::Comprometido, '1'))->toThrow(ImputacionRechazada::class, 'máximo');
});

test('el código de un centro de costo va en mayúsculas y sin espacios', function (string $codigo) {
    expect(fn () => DB::transaction(fn () => CentroCosto::factory()->create(['codigo' => $codigo])))->toThrow(QueryException::class);
})->with(['minúsculas' => 'adm2', 'con espacio' => 'AD M', 'vacío' => '']);

test('cada cambio de destino o de monto queda en la bitácora', function () {
    $linea = imLinea();
    $imputacion = imDestinar($linea);
    imMover($linea, MomentoImputacion::Comprometido, '250');

    expect(AuditoriaCambio::query()->where('auditable_type', 'imputacion')->where('auditable_id', $imputacion->id)->count())->toBeGreaterThan(0);
});
