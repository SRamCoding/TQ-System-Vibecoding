<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\LineaApu;
use InvalidArgumentException;
use RoundingMode;

/**
 * Las cuentas del presupuesto (plan 2.9), sin base de datos. Todo en texto decimal con bcmath; el redondeo es a la
 * mitad hacia arriba. D4: la cantidad efectiva va a 5 decimales y es la que vale. D5: parcial del APU a 4, P.U. a los
 * decimales del parámetro (2), parcial de la partida = metrado × P.U. a 2; la lista de insumos suma los aportes de
 * cada partida (metrado × parcial del APU, a 2) y la diferencia con el costo directo va a la vista como ajuste.
 */
final class CalculoPresupuesto
{
    /** Material y subcontrato: neta × (1 + desperdicio). 1.00 con 5 % → 1.05000. */
    public static function cantidadPorCantidad(string $neta, string $desperdicioPct): string
    {
        return self::redondear(bcmul($neta, bcadd('1', bcdiv($desperdicioPct, '100', 10), 10), 12), 5);
    }

    /**
     * Mano de obra y equipo: cuadrilla × horas de la unidad ÷ rendimiento. 20 m/h → 0.05000 h; 160 m/día (jornada de
     * 8 h) con cuadrilla 2 → 0.10000 h; 15 m/h → 0.06667 h.
     */
    public static function cantidadPorRendimiento(string $rendimiento, string $unidad, string $cuadrilla, ?string $horasJornada): string
    {
        if (bccomp($rendimiento, '0', 4) <= 0) {
            throw new InvalidArgumentException('El rendimiento va mayor que cero.');
        }

        $horas = match ($unidad) {
            LineaApu::POR_HORA => '1',
            LineaApu::POR_JORNADA => $horasJornada ?? throw new InvalidArgumentException('Por jornada hacen falta las horas de la jornada.'),
            default => throw new InvalidArgumentException("La unidad del rendimiento es «hora» o «jornada», no «{$unidad}»."),
        };

        return self::redondear(bcdiv(bcmul($cuadrilla, $horas, 10), $rendimiento, 12), 5);
    }

    /** Parcial de una línea del APU: efectiva × precio, a 4 decimales. */
    public static function parcialApu(string $cantidad, string $precio): string
    {
        return self::redondear(bcmul($cantidad, $precio, 12), 4);
    }

    /**
     * P.U. de una partida con APU: la suma de sus parciales, a los decimales del parámetro.
     *
     * @param  list<string>  $parciales
     */
    public static function precioUnitario(array $parciales, int $decimales): string
    {
        $suma = '0';

        foreach ($parciales as $parcial) {
            $suma = bcadd($suma, $parcial, 4);
        }

        return self::redondear($suma, $decimales);
    }

    /** Metrado × P.U., a 2 decimales (lo que la entidad revisa en la oferta). */
    public static function parcialPartida(string $metrado, string $precioUnitario): string
    {
        return self::redondear(bcmul($metrado, $precioUnitario, 12), 2);
    }

    /** Lo que una línea del APU aporta a la lista de insumos en su partida: metrado × parcial del APU, a 2. */
    public static function aporte(string $metrado, string $parcialApu): string
    {
        return self::redondear(bcmul($metrado, $parcialApu, 12), 2);
    }

    /** Cuánto de un insumo lleva una partida: metrado × cantidad efectiva, a 5 decimales. */
    public static function cantidadEnPartida(string $metrado, string $cantidad): string
    {
        return self::redondear(bcmul($metrado, $cantidad, 12), 5);
    }

    /**
     * Gastos generales y utilidad sobre el costo directo; IGV sobre los tres (v4, flujo 1: 17,750.00 → 1,775.00,
     * 1,420.00, 3,770.10 y 24,715.10).
     */
    public static function totales(string $costoDirecto, string $gastosGeneralesPct, string $utilidadPct, string $igvPct): Totales
    {
        $gastosGenerales = self::porcentaje($costoDirecto, $gastosGeneralesPct);
        $utilidad = self::porcentaje($costoDirecto, $utilidadPct);
        $subtotal = bcadd(bcadd($costoDirecto, $gastosGenerales, 2), $utilidad, 2);
        $igv = self::porcentaje($subtotal, $igvPct);

        return new Totales($costoDirecto, $gastosGenerales, $utilidad, $igv, bcadd($subtotal, $igv, 2));
    }

    public static function redondear(string $numero, int $decimales): string
    {
        return bcround($numero, $decimales, RoundingMode::HalfAwayFromZero);
    }

    private static function porcentaje(string $base, string $pct): string
    {
        return self::redondear(bcdiv(bcmul($base, $pct, 6), '100', 8), 2);
    }
}
