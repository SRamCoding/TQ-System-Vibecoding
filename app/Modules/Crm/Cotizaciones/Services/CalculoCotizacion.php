<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use RoundingMode;

/**
 * El cálculo de una versión de la cotización (plan 2.22, D6, D9 y D14), puro y con bcmath. Por línea: bruto = cantidad
 * × precio; subtotal = bruto × (1 − descuento), a 2 decimales; descuento = bruto − subtotal; costo = cantidad × costo
 * unitario, a 2. La versión suma las líneas; IGV = subtotal × tasa, a 2; total = subtotal + IGV. Margen = (subtotal −
 * costo) / subtotal, en % a 2 decimales. Redondeo comercial (la mitad se aleja del cero), como el presupuesto.
 */
final class CalculoCotizacion
{
    /**
     * @param  list<LineaParaCalculo>  $lineas
     */
    public static function calcular(array $lineas, string $igvPct): TotalesCotizacion
    {
        $calculadas = array_map(self::linea(...), $lineas);
        $suma = fn (string $campo): string => array_reduce($calculadas, fn (string $acumulado, LineaCalculada $l): string => bcadd($acumulado, $l->{$campo}, 2), '0.00');
        $subtotal = $suma('subtotal');
        $costo = $suma('costo');
        $igv = self::redondear(bcdiv(bcmul($subtotal, $igvPct, 6), '100', 8), 2);

        return new TotalesCotizacion(
            $calculadas,
            $subtotal,
            $suma('descuento'),
            $igv,
            bcadd($subtotal, $igv, 2),
            $costo,
            self::margen($subtotal, $costo),
        );
    }

    public static function linea(LineaParaCalculo $linea): LineaCalculada
    {
        $bruto = self::redondear(bcmul($linea->cantidad, $linea->precioUnitario, 8), 2);
        $factor = bcsub('1', bcdiv($linea->descuentoPct, '100', 8), 8);
        $subtotal = self::redondear(bcmul(bcmul($linea->cantidad, $linea->precioUnitario, 8), $factor, 10), 2);
        $costo = self::redondear(bcmul($linea->cantidad, $linea->costoUnitario, 8), 2);

        return new LineaCalculada($bruto, bcsub($bruto, $subtotal, 2), $subtotal, $costo, self::margen($subtotal, $costo));
    }

    /** (precio − costo) / precio, en %; nulo sin precio. */
    public static function margen(string $precio, string $costo): ?string
    {
        if (bccomp($precio, '0', 2) === 0) {
            return null;
        }

        return self::redondear(bcmul(bcdiv(bcsub($precio, $costo, 2), $precio, 10), '100', 8), 2);
    }

    public static function redondear(string $numero, int $decimales): string
    {
        return bcround($numero, $decimales, RoundingMode::HalfAwayFromZero);
    }
}
