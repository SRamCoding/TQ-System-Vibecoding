<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Services\CalculoCotizacion;
use App\Modules\Crm\Cotizaciones\Services\LineaParaCalculo;

/*
 * El cálculo de la cotización (plan 2.22, paso 4) con los números del v4 y del diseño (D17).
 */

test('D17.1: COT-2026-0184 v2 da subtotal 51 644.00, descuentos 4 326.00, IGV 9 295.92, total 60 939.92 y margen 19.40 %', function () {
    $t = CalculoCotizacion::calcular([
        new LineaParaCalculo('24', '1180.00', '5', '807.12'),     // cámara
        new LineaParaCalculo('3', '4250.00', '12', '3104.20'),    // switch
        new LineaParaCalculo('1', '6900.00', '20', '5188.80'),    // NVR
        new LineaParaCalculo('1', '8000.00', '0', '7752.78'),     // instalación, por catalogar
    ], '18.00');

    expect(array_map(fn ($l) => $l->subtotal, $t->lineas))->toBe(['26904.00', '11220.00', '5520.00', '8000.00'])
        ->and(array_map(fn ($l) => $l->margenPct, $t->lineas))->toBe(['28.00', '17.00', '6.00', '3.09'])
        ->and([$t->subtotal, $t->descuento, $t->igv, $t->total])->toBe(['51644.00', '4326.00', '9295.92', '60939.92'])
        ->and([$t->costo, $t->margenPct])->toBe(['41625.06', '19.40']);
});

test('D17.3: la línea del presupuesto PRE-2026-031 (20 945.00, costo 19 525.00) da IGV 3 770.10 y total 24 715.10', function () {
    $t = CalculoCotizacion::calcular([new LineaParaCalculo('1', '20945.00', '0', '19525.00')], '18.00');

    expect([$t->subtotal, $t->descuento, $t->igv, $t->total, $t->costo, $t->margenPct])
        ->toBe(['20945.00', '0.00', '3770.10', '24715.10', '19525.00', '6.78']);
});

test('sin líneas todo es cero y el margen no existe; una línea regalada tampoco tiene margen', function () {
    $vacia = CalculoCotizacion::calcular([], '18.00');
    $regalo = CalculoCotizacion::linea(new LineaParaCalculo('2', '50.00', '100', '30.00'));

    expect([$vacia->subtotal, $vacia->igv, $vacia->total, $vacia->margenPct])->toBe(['0.00', '0.00', '0.00', null])
        ->and([$regalo->bruto, $regalo->descuento, $regalo->subtotal, $regalo->costo, $regalo->margenPct])->toBe(['100.00', '100.00', '0.00', '60.00', null]);
});

test('redondea a 2 decimales con la mitad alejándose del cero, y el margen puede ser negativo', function () {
    $l = CalculoCotizacion::linea(new LineaParaCalculo('3', '0.3350', '0', '0.5000'));

    expect([$l->subtotal, $l->costo, $l->margenPct])->toBe(['1.01', '1.50', '-48.51']);
});
