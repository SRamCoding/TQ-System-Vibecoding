<?php

declare(strict_types=1);

use App\Modules\Crm\Oportunidades\Filament\MontoCorto;

/*
 * El monto corto de las vistas de Oportunidades (plan 2.21c, D16): entero debajo de mil, «k» con un decimal solo si
 * importa debajo de 100 mil, «k» entero hasta el millón y «M» con hasta dos decimales. Desde el texto decimal, sin float.
 */

test('los bordes de cada escalón', function (?string $monto, string $esperado) {
    expect(MontoCorto::de($monto, 'S/'))->toBe($esperado);
})->with([
    'sin monto' => [null, '—'],
    'cero' => ['0.00', 'S/ 0'],
    '850' => ['850.00', 'S/ 850'],
    '999' => ['999.00', 'S/ 999'],
    '999.49 se queda' => ['999.49', 'S/ 999'],
    '999.50 sube a 1k' => ['999.50', 'S/ 1k'],
    '1000' => ['1000.00', 'S/ 1k'],
    '1050' => ['1050.00', 'S/ 1.1k'],
    '27000' => ['27000.00', 'S/ 27k'],
    '27500' => ['27500.00', 'S/ 27.5k'],
    '27040 no importa el decimal' => ['27040.00', 'S/ 27k'],
    '99999' => ['99999.00', 'S/ 100k'],
    '100000' => ['100000.00', 'S/ 100k'],
    '150000' => ['150000.00', 'S/ 150k'],
    '150600' => ['150600.00', 'S/ 151k'],
    '999999' => ['999999.00', 'S/ 1M'],
    '1000000' => ['1000000.00', 'S/ 1M'],
    '1200000' => ['1200000.00', 'S/ 1.2M'],
    '1250000' => ['1250000.00', 'S/ 1.25M'],
    '1254999' => ['1254999.00', 'S/ 1.25M'],
    '2500000000' => ['2500000000.00', 'S/ 2,500M'],
    'negativo' => ['-27500.00', 'S/ -27.5k'],
]);

test('sin símbolo, solo el número; con otra moneda, su símbolo', function () {
    expect(MontoCorto::de('27500.00', null))->toBe('27.5k')
        ->and(MontoCorto::de('1250000.00', 'US$'))->toBe('US$ 1.25M');
});
