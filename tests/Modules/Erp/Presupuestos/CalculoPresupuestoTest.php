<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Services\CalculoPresupuesto;
use App\Modules\Erp\Presupuestos\Services\LineaParaLista;
use App\Modules\Erp\Presupuestos\Services\ListaDeInsumos;
use App\Modules\Erp\Presupuestos\Services\PartidaParaLista;

/*
 * Las cuentas del presupuesto sin base de datos (plan 2.9, D4, D5 y D10). El escenario completo del flujo 1, con la
 * base, está en PresupuestoFlujo1Test.
 */

test('material: la cantidad efectiva suma el desperdicio (1.00 con 5 % da 1.05)', function () {
    expect(CalculoPresupuesto::cantidadPorCantidad('1', '5'))->toBe('1.05000')
        ->and(CalculoPresupuesto::cantidadPorCantidad('2.5', '0'))->toBe('2.50000');
});

test('mano de obra: cuadrilla × horas de la unidad ÷ rendimiento, a 5 decimales', function () {
    expect(CalculoPresupuesto::cantidadPorRendimiento('20', 'hora', '1', null))->toBe('0.05000')
        ->and(CalculoPresupuesto::cantidadPorRendimiento('160', 'jornada', '1', '8'))->toBe('0.05000')
        ->and(CalculoPresupuesto::cantidadPorRendimiento('160', 'jornada', '2', '8'))->toBe('0.10000')
        ->and(CalculoPresupuesto::cantidadPorRendimiento('15', 'hora', '1', null))->toBe('0.06667');
});

test('el APU de 02.01 del flujo 1 da P.U. 4.02 y 600 m dan 2,412.00', function () {
    $cable = CalculoPresupuesto::parcialApu(CalculoPresupuesto::cantidadPorCantidad('1', '5'), '2.40');
    $tecnico = CalculoPresupuesto::parcialApu('0.05000', '18.00');
    $ayudante = CalculoPresupuesto::parcialApu('0.05000', '12.00');
    $pu = CalculoPresupuesto::precioUnitario([$cable, $tecnico, $ayudante], 2);

    expect([$cable, $tecnico, $ayudante, $pu])->toBe(['2.5200', '0.9000', '0.6000', '4.02'])
        ->and(CalculoPresupuesto::parcialPartida('600', $pu))->toBe('2412.00');
});

test('los totales del flujo 1: gastos generales y utilidad sobre el costo directo, IGV sobre los tres', function () {
    $t = CalculoPresupuesto::totales('17750.00', '10', '8', '18');

    expect([$t->costoDirecto, $t->gastosGenerales, $t->utilidad, $t->igv, $t->total])
        ->toBe(['17750.00', '1775.00', '1420.00', '3770.10', '24715.10']);
});

test('con metrado decimal, la diferencia de redondeo va en una línea de ajuste con su partida', function () {
    // 12.345 × 1.32 = 16.30, pero 12.345 × 1.02 = 12.59 y 12.345 × 0.30 = 3.70 suman 16.29.
    $lista = ListaDeInsumos::desde([new PartidaParaLista('02.01', 'Cableado', '12.345', 'm', CalculoPresupuesto::parcialPartida('12.345', '1.32'), [
        new LineaParaLista(1, 'CAB', 'Cable', 'material', 'm', false, '1.00000', '1.0200'),
        new LineaParaLista(2, 'TEC', 'Técnico', 'mano_obra', 'h', false, '0.02000', '0.3000'),
    ])]);

    expect($lista->total)->toBe('16.30')
        ->and(array_map(fn ($i) => $i->monto, $lista->insumos))->toBe(['3.70', '12.59'])
        ->and($lista->ajuste)->toBe('0.01')
        ->and($lista->ajustes)->toHaveCount(1)
        ->and([$lista->ajustes[0]->codigo, $lista->ajustes[0]->descripcion, $lista->ajustes[0]->metrado, $lista->ajustes[0]->monto])->toBe(['02.01', 'Cableado', '12.345', '0.01']);
});

test('lo sin detallar va aparte, solo con su monto', function () {
    $lista = ListaDeInsumos::desde([new PartidaParaLista('01.01', 'Obras civiles', '1', 'glb', '8500.00', [
        new LineaParaLista(9, 'SIN-DETALLE', 'Precio sin detallar', 'subcontrato', 'glb', true, '1.00000', '8500.0000'),
    ])]);

    expect($lista->insumos)->toBe([])
        ->and($lista->sinDetallar)->toBe('8500.00')
        ->and($lista->ajuste)->toBe('0.00')
        ->and($lista->total)->toBe('8500.00');
});
