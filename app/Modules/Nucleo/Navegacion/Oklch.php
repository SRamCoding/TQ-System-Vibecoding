<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Cuentas de color en oklch (plan 1.4r): el matiz de un color de la marca, cuánta intensidad (croma) cabe en la pantalla
 * a una claridad dada y el contraste entre dos colores (WCAG). Así el estilo de las cajas por estación no pide colores
 * que la pantalla no puede mostrar (el navegador los recortaría y cambiaría el tono) y se puede probar que el nombre se
 * lee.
 */
final class Oklch
{
    /** El matiz (0–360) de un color `#RRGGBB`. */
    public static function matiz(string $hex): float
    {
        [$l, $a, $b] = self::oklab($hex);

        return fmod(rad2deg(atan2($b, $a)) + 360, 360);
    }

    /** La croma más alta que cabe en sRGB con esa claridad y ese matiz, con un margen del 2 %. */
    public static function cromaMaxima(float $claridad, float $matiz): float
    {
        [$bajo, $alto] = [0.0, 0.5];

        for ($i = 0; $i < 30; $i++) {
            $medio = ($bajo + $alto) / 2;
            self::dentroDeSrgb($claridad, $medio, $matiz) ? $bajo = $medio : $alto = $medio;
        }

        return $bajo * 0.98;
    }

    /** Luminancia relativa (WCAG) de un color oklch, recortado a sRGB. */
    public static function luminancia(float $claridad, float $croma, float $matiz): float
    {
        [$r, $g, $b] = array_map(fn (float $canal): float => min(1, max(0, $canal)), self::srgbLineal($claridad, $croma, $matiz));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** Contraste WCAG entre dos luminancias: de 1 a 21. */
    public static function contraste(float $luminanciaA, float $luminanciaB): float
    {
        return (max($luminanciaA, $luminanciaB) + 0.05) / (min($luminanciaA, $luminanciaB) + 0.05);
    }

    private static function dentroDeSrgb(float $claridad, float $croma, float $matiz): bool
    {
        foreach (self::srgbLineal($claridad, $croma, $matiz) as $canal) {
            if ($canal < -0.0001 || $canal > 1.0001) {
                return false;
            }
        }

        return true;
    }

    /** @return array{float, float, float} */
    private static function srgbLineal(float $claridad, float $croma, float $matiz): array
    {
        $a = $croma * cos(deg2rad($matiz));
        $b = $croma * sin(deg2rad($matiz));
        $l = ($claridad + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($claridad - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($claridad - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /** @return array{float, float, float} */
    private static function oklab(string $hex): array
    {
        [$r, $g, $b] = array_map(function (string $par): float {
            $canal = hexdec($par) / 255;

            return $canal <= 0.04045 ? $canal / 12.92 : (($canal + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }
}
