<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Los adornos de las cajas del inicio por estación (plan 1.4t): las hojas de otoño en la esquina de abajo a la izquierda
 * y la escarcha de invierno. Cada caja tiene los suyos, siempre los mismos (salen de su clave): otro color, otra forma,
 * otro giro, otra disposición. Son SVG sin texto ni interacción; el CSS de cajas-modulos los muestra solo en su
 * estación.
 */
final class AdornosDeEstacion
{
    /** Colores de hoja de otoño: rojo, naranja, ámbar, ocre, granate y marrón. Decoración, igual en todas las áreas. */
    private const array COLORES_DE_HOJA = [
        'oklch(0.56 0.17 32)',
        'oklch(0.68 0.16 55)',
        'oklch(0.78 0.15 80)',
        'oklch(0.63 0.11 70)',
        'oklch(0.46 0.13 22)',
        'oklch(0.50 0.08 50)',
    ];

    /**
     * Formas de hoja en un cuadro de 20 × 20, con la punta arriba y el tallo abajo en (10, 19): ovalada con punta,
     * redondeada y de roble (borde ondulado). Sin la de arce: chica, parecía una estrella (pedido del desarrollador).
     */
    private const array FORMAS_DE_HOJA = [
        'M10 1.5 C14.5 4.5 15 10.5 10 16 C5 10.5 5.5 4.5 10 1.5 Z',
        'M10 2 C15.5 3 16.5 9.5 10 16 C4 11 4.5 4 10 2 Z',
        'M10 1.5 C11.5 2.5 12.8 3 12.2 4.6 C14 4.4 14.6 5.8 13.4 7 C15.2 7.2 15.4 9 13.8 10 C15 11 14.4 12.6 12.6 12.4 '
            .'C12.8 14 11.6 15.4 10 16 C8.4 15.4 7.2 14 7.4 12.4 C5.6 12.6 5 11 6.2 10 C4.6 9 4.8 7.2 6.6 7 '
            .'C5.4 5.8 6 4.4 7.8 4.6 C7.2 3 8.5 2.5 10 1.5 Z',
    ];

    /** El tallo, la nervadura central y dos pares de nervaduras laterales, comunes a las tres formas. */
    private const string NERVADURAS = 'M10 19 L10 4 M10 8 L12.6 6.4 M10 8 L7.4 6.4 M10 11.5 L13 9.8 M10 11.5 L7 9.8';

    /** Dónde puede caer cada hoja en la esquina (centro x, y, en un lienzo de 44 × 38). */
    private const array LUGARES = [[9, 29], [21, 31], [10, 17], [30, 33], [20, 21], [4, 22]];

    public static function hojas(string $clave): string
    {
        $azar = self::azar($clave);
        $cantidad = 3 + $azar(2);
        $lugares = self::LUGARES;
        $hojas = '';

        for ($n = 0; $n < $cantidad; $n++) {
            [$x, $y] = array_splice($lugares, $azar(count($lugares)), 1)[0];
            $giro = $azar(360);
            $escala = 0.7 + $azar(25) / 100;
            $color = self::COLORES_DE_HOJA[$azar(count(self::COLORES_DE_HOJA))];
            $forma = self::FORMAS_DE_HOJA[$azar(count(self::FORMAS_DE_HOJA))];
            $hojas .= sprintf(
                '<g transform="translate(%d %d) rotate(%d) scale(%.2F) translate(-10 -10)"><path d="%s" fill="%s"/><path d="%s" class="tq-hoja-vena"/></g>',
                $x, $y, $giro, $escala, $forma, $color, self::NERVADURAS,
            );
        }

        return '<svg class="tq-caja-hojas" viewBox="0 0 44 38" aria-hidden="true" focusable="false">'.$hojas.'</svg>';
    }

    /**
     * Las hojas que caen por la barra superior en otoño (plan 1.4t): una de cada forma con colores distintos, cada una en
     * su SVG de 20 × 20.
     *
     * @return list<string>
     */
    public static function hojasQueCaen(): array
    {
        $hojas = [];

        foreach (self::COLORES_DE_HOJA as $n => $color) {
            $hojas[] = sprintf(
                '<svg class="tq-hoja-cae" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="%s" fill="%s"/><path d="%s" class="tq-hoja-vena"/></svg>',
                self::FORMAS_DE_HOJA[$n % count(self::FORMAS_DE_HOJA)], $color, self::NERVADURAS,
            );
        }

        return $hojas;
    }

    /**
     * La escarcha de invierno: cristales que crecen desde la esquina de arriba a la derecha con unos cristalitos sueltos
     * y, en algunas cajas, otra escarcha más chica desde la de abajo a la izquierda. El color lo da el CSS
     * (`currentColor`).
     */
    public static function hielo(string $clave): string
    {
        $azar = self::azar($clave.'-hielo');
        $escarcha = '<path d="M48 0 L22 8 M40 2.5 L36 -2 M40 2.5 L38 8 M32 5.5 L28 1 M32 5.5 L30 11 '
            .'M48 0 L32 24 M43 7.5 L46 13 M43 7.5 L37 9 M38 15 L42 20 M38 15 L31 15 '
            .'M48 0 L46 30 M47.5 9 L44 12 M47 17 L43 20 M46.5 24 L49 27"/>';
        $cristales = '';
        $cantidad = 2 + $azar(2);

        for ($n = 0; $n < $cantidad; $n++) {
            [$x, $y] = [10 + $azar(24), 26 + $azar(16)];
            $cristales .= sprintf('<path d="M%d %d h4 M%d %.1F l2 3.4 M%d %.1F l2 -3.4" class="tq-cristal"/>', $x - 2, $y, $x - 1, $y - 1.7, $x - 1, $y + 1.7);
        }

        $svg = fn (string $clase, string $trazos): string => '<svg class="tq-caja-hielo '.$clase.'" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'.$trazos.'</svg>';

        return $svg('tq-hielo-arriba', $escarcha.$cristales).($azar(2) === 1 ? $svg('tq-hielo-abajo', $escarcha) : '');
    }

    /** Un generador de números fijo para la clave: la misma caja, los mismos adornos. */
    private static function azar(string $clave): \Closure
    {
        $semilla = crc32($clave);

        return function (int $hasta) use (&$semilla): int {
            $semilla = ($semilla * 1103515245 + 12345) & 0x7FFFFFFF;

            return intdiv($semilla, 65536) % $hasta;
        };
    }
}
