<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\CentroDeCosto;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Imputacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\ArbolDePrueba;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;

/*
 * Lo que solo la versión real de C1 revisa (plan 2.1, D6 y D7): imputar de verdad, la moneda, el redondeo, la PC-99
 * con su lista de motivos y su centro de referencia, y las reglas de la base. El contrato compartido está en
 * ContratoPartidasTest.php.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
    ModeloAuditado::crearTabla();
});

function ptLinea(): ModeloAuditado
{
    return ModeloAuditado::query()->create(['nombre' => 'Línea '.fake()->unique()->numerify('####')]);
}

function ptMotivoNoPresupuestado(): int
{
    return (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_NO_PRESUPUESTADO))->value('id');
}

test('una imputación en dólares a una partida de un proyecto en soles se permite y el saldo la suma en soles', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-12'), '3.3300', '3.3335', OrigenTipoCambio::Manual);
    $a = ArbolDePrueba::armar([['PC-01', 'Equipos importados', '10.00', null]]);
    $imputaciones = app(ImputacionService::class);

    foreach ([1, 2] as $vez) {
        $linea = ptLinea();
        $imputaciones->destinar($linea, app(PartidasService::class)->destino($a->partidas['PC-01']), CarbonImmutable::parse('2026-11-12'), 'USD');
        $imputaciones->mover($linea, MomentoImputacion::Comprometido, '0.05');
    }

    // Por línea: 0.05 × 3.3335 = 0.166675 → 0.17, y dos dan 0.34. Sobre el total: 0.33335 → 0.33. Manda el total (D7).
    expect(app(PartidasService::class)->saldo($a->partidas['PC-01'])->comprometido)->toBe('0.33')
        ->and(app(PartidasService::class)->saldo($a->partidas['PC-01'])->disponible)->toBe('9.67');
});

test('la PC-99 pide un motivo de su lista; el centro de referencia es opcional y solo para ella', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);
    $c1 = app(PartidasService::class);
    $deOtraLista = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->value('id');
    $adm = (int) CentroCosto::query()->where('codigo', 'ADM')->value('id');

    expect(fn () => $c1->destino($a->partidas['PC-99'], $deOtraLista, 'Taxi'))->toThrow(ImputacionRechazada::class, 'no es de la lista de gastos no presupuestados')
        ->and(fn () => $c1->destino($a->partidas['PC-01'], centroCostoId: $adm))->toThrow(ImputacionRechazada::class, 'solo para la PC-99');

    $linea = ptLinea();
    $imputacion = app(ImputacionService::class)->destinar($linea, $c1->destino($a->partidas['PC-99'], ptMotivoNoPresupuestado(), 'Taxi imprevisto a la obra', $adm), CarbonImmutable::parse('2026-11-12'), 'PEN');

    expect([$imputacion->partida_control_id, $imputacion->proyecto_id, $imputacion->centro_costo_id, $imputacion->comentario])
        ->toBe([$a->partidas['PC-99'], $a->proyectoId, $adm, 'Taxi imprevisto a la obra']);
});

test('los totales por centro de costo dejan fuera lo que tiene partida, aunque lleve centro de referencia', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);
    $imputaciones = app(ImputacionService::class);
    $adm = (int) CentroCosto::query()->where('codigo', 'ADM')->value('id');
    $directa = ptLinea();
    $imputaciones->destinar($directa, CentroDeCosto::porCodigo('ADM'), CarbonImmutable::parse('2026-11-12'), 'PEN');
    $imputaciones->mover($directa, MomentoImputacion::Comprometido, '40');
    $taxi = ptLinea();
    $imputaciones->destinar($taxi, app(PartidasService::class)->destino($a->partidas['PC-99'], ptMotivoNoPresupuestado(), 'Taxi', $adm), CarbonImmutable::parse('2026-11-12'), 'PEN');
    $imputaciones->mover($taxi, MomentoImputacion::Comprometido, '25');

    expect(collect($imputaciones->totalesPorCentroDeCosto())->firstWhere('centro.codigo', 'ADM')['comprometido'])->toBe('40.00')
        ->and(app(PartidasService::class)->saldo($a->partidas['PC-99'])->comprometido)->toBe('25.00');
});

test('cambiar de destino limpia lo que el anterior dejó', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);
    $linea = ptLinea();
    $imputaciones = app(ImputacionService::class);
    $imputaciones->destinar($linea, app(PartidasService::class)->destino($a->partidas['PC-99'], ptMotivoNoPresupuestado(), 'Taxi'), CarbonImmutable::parse('2026-11-12'), 'PEN');

    $otra = $imputaciones->destinar($linea, CentroDeCosto::porCodigo('ADM'), CarbonImmutable::parse('2026-11-12'), 'PEN');

    expect([$otra->partida_control_id, $otra->proyecto_id, $otra->motivo_id, $otra->comentario])->toBe([null, null, null, null]);
});

test('la base no deja una línea con la partida de un proyecto y el id de otro, ni una partida sin proyecto', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);
    $b = ArbolDePrueba::armar([['PC-01', 'Otra obra', '100.00', null]]);
    $linea = ptLinea();
    app(ImputacionService::class)->destinar($linea, app(PartidasService::class)->destino($a->partidas['PC-01']), CarbonImmutable::parse('2026-11-12'), 'PEN');
    $id = Imputacion::query()->where('origen_id', $linea->id)->value('id');

    expect(fn () => DB::transaction(fn () => DB::table('imputaciones')->where('id', $id)->update(['proyecto_id' => $b->proyectoId])))->toThrow(QueryException::class, 'foreign key')
        ->and(fn () => DB::transaction(fn () => DB::table('imputaciones')->where('id', $id)->update(['proyecto_id' => null, 'centro_costo_id' => CentroCosto::query()->value('id')])))->toThrow(QueryException::class, 'imputaciones_destino_check');
});
