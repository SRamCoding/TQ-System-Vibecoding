<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

/**
 * Un monto en notación corta para las vistas de Oportunidades (plan 2.21c, D16): «S/ 27k» en lugar de «S/ 27,000.00».
 * Solo para mostrar: el monto exacto va siempre al lado, en el `title` y en lo que lee un lector de pantalla
 * (`PanelesOportunidad::dinero`, el componente `<x-oportunidades::monto>`). Las tablas emergentes, la Lista y los
 * formularios siguen con el monto completo. Trabaja con el texto decimal y bcmath: nunca float.
 *
 * - Menos de mil: entero, redondeado («S/ 850»).
 * - Miles, debajo de 100 mil: «k» con un decimal solo si importa («S/ 27k», «S/ 27.5k»); desde 100 mil, sin decimal
 *   («S/ 150k»).
 * - Millones: «M» con hasta dos decimales, sin ceros de sobra («S/ 1M», «S/ 1.2M», «S/ 1.25M»).
 * - El punto decimal, como en el resto del sistema. Si al redondear llega al escalón de arriba, sube («S/ 1k»,
 *   «S/ 1M»). Sin monto, «—».
 */
final class MontoCorto
{
    public static function de(?string $monto, ?string $simbolo): string
    {
        if ($monto === null || trim($monto) === '') {
            return '—';
        }

        $valor = bcadd(trim($monto), '0', 6);
        $negativo = bccomp($valor, '0', 6) < 0;
        $absoluto = ltrim($valor, '-');
        $texto = self::sinSigno($absoluto);
        $signo = $negativo && $texto !== '0' ? '-' : '';

        return trim(($simbolo ?? '').' '.$signo.$texto);
    }

    private static function sinSigno(string $absoluto): string
    {
        $entero = self::redondear($absoluto, 0);

        if (bccomp($entero, '1000', 0) < 0) {
            return $entero;
        }

        if (bccomp($absoluto, '100000', 6) < 0) {
            $miles = self::redondear(bcdiv($absoluto, '1000', 6), 1);

            if (bccomp($miles, '100', 1) < 0) {
                return self::sinCeros($miles).'k';
            }
        }

        $miles = self::redondear(bcdiv($absoluto, '1000', 6), 0);

        if (bccomp($miles, '1000', 0) < 0) {
            return $miles.'k';
        }

        $millones = self::sinCeros(self::redondear(bcdiv($absoluto, '1000000', 6), 2));
        [$enteros, $decimales] = array_pad(explode('.', $millones, 2), 2, null);
        $conMiles = strrev(implode(',', str_split(strrev($enteros), 3)));

        return $conMiles.($decimales === null ? '' : '.'.$decimales).'M';
    }

    /** Redondeo a la mitad hacia arriba de un número no negativo. */
    private static function redondear(string $absoluto, int $decimales): string
    {
        return bcadd($absoluto, '0.'.str_repeat('0', $decimales).'5', $decimales);
    }

    private static function sinCeros(string $numero): string
    {
        return str_contains($numero, '.') ? rtrim(rtrim($numero, '0'), '.') : $numero;
    }
}
