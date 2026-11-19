<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\InsumoEnLista;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Escenario de aceptación del flujo 1 (v4, parte 8, F1): «Se aprueba el presupuesto PRE-2026-031 → costo directo
 * 17,750.00; gastos generales 1,775.00; utilidad 1,420.00; IGV 3,770.10; total 24,715.10; la lista de insumos suma
 * 17,750.00». El número real lo da la numeración (PRE-AAAA-NNNN).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

test('F1: el presupuesto de la Plaza de Armas se aprueba con los números del v4', function () {
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();

    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $r = app(PresupuestosService::class)->resumen($p->presupuesto->id);

    expect([$r->estado, $r->esVigente, $r->proyectoId])->toBe([DefinicionPresupuesto::APROBADO, true, $proyecto])
        ->and($r->numero)->toMatch('/^PRE-\d{4}-\d{4}$/')
        ->and([$r->costoDirecto, $r->gastosGenerales, $r->utilidad, $r->igv, $r->total])->toBe(['17750.00', '1775.00', '1420.00', '3770.10', '24715.10'])
        ->and(array_map(fn ($partida) => [$partida->codigo, $partida->precioUnitario, $partida->parcial], $r->partidas))->toBe([
            ['01.01', '643.00', '6430.00'],
            ['02.01', '4.02', '2412.00'],
            ['02.02', '1090.00', '4360.00'],
            ['03.01', '4308.00', '4308.00'],
            ['03.02', '240.00', '240.00'],
        ]);

    $porTitulo = [];

    foreach ($r->partidas as $partida) {
        $porTitulo[$partida->tituloCodigo.' '.$partida->tituloNombre] = bcadd($porTitulo[$partida->tituloCodigo.' '.$partida->tituloNombre] ?? '0', $partida->parcial, 2);
    }

    expect($porTitulo)->toBe(['01 CÁMARAS' => '6430.00', '02 CABLEADO Y POSTES' => '6772.00', '03 CENTRO DE MONITOREO' => '4548.00']);

    $lista = app(ConsultaPresupuestos::class)->listaDeInsumos($p->presupuesto->id);
    $cantidades = collect($lista->insumos)->mapWithKeys(fn (InsumoEnLista $i) => [$i->codigo => $i->cantidad])->all();
    $suma = array_reduce($lista->insumos, fn (string $s, InsumoEnLista $i): string => bcadd($s, $i->monto, 2), '0');

    expect($suma)->toBe('17750.00')
        ->and([$lista->sinDetallar, $lista->ajuste, $lista->total])->toBe(['0.00', '0.00', '17750.00'])
        ->and([$cantidades['CAB-UTP6'], $cantidades['MO-TEC'], $cantidades['MO-AYU']])->toBe(['630.00000', '80.00000', '74.00000'])
        ->and($lista->insumos[0]->codigo)->toBe('MO-AYU');
});

test('el APU de 02.01 guarda lo escrito y la cantidad efectiva: cable 1.00 + 5 %, técnico 20 m por hora', function () {
    $p = PresupuestoDePrueba::flujo1();
    $lineas = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['02.01'])->with('insumo')->get()->keyBy(fn ($l) => $l->insumo->codigo);

    expect([$lineas['CAB-UTP6']->tipo_cantidad, $lineas['CAB-UTP6']->cantidad_neta, $lineas['CAB-UTP6']->desperdicio_pct, $lineas['CAB-UTP6']->cantidad, $lineas['CAB-UTP6']->parcial])
        ->toBe(['cantidad', '1.00000', '5.00', '1.05000', '2.5200'])
        ->and([$lineas['MO-TEC']->tipo_cantidad, $lineas['MO-TEC']->rendimiento, $lineas['MO-TEC']->unidad_rendimiento, $lineas['MO-TEC']->cuadrilla, $lineas['MO-TEC']->cantidad, $lineas['MO-TEC']->parcial])
        ->toBe(['rendimiento', '20.0000', 'hora', '1.00', '0.05000', '0.9000']);

    // Por jornada guarda las horas de la jornada con que se calculó (02.02: 2 postes por jornada de 8 h → 4 h).
    $poste = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['02.02'])->whereHas('insumo', fn ($q) => $q->where('codigo', 'MO-TEC'))->firstOrFail();

    expect([$poste->unidad_rendimiento, $poste->horas_jornada, $poste->cantidad])->toBe(['jornada', '8.00', '4.00000']);
});
