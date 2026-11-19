<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Enums\Temporada;

/**
 * El estilo de las cajas del inicio según la estación (plan 1.4r): con la estación cambia la caja entera (fondo,
 * degradado, franja, ícono y su ficha), pero nunca el matiz de su área (el del CRM sí cambia con el tema, como el
 * logo). Aquí viven, por estación y tema, la claridad y la intensidad (croma) de cada parte; el matiz sale de
 * ColoresMarca. Las variables CSS se arman por área, así que una
 * caja nueva las hereda sin declarar nada.
 *
 * Dos ajustes sobre los valores de la vista previa (anotados en el plan, D3 y D4): la croma se recorta a la que la
 * pantalla puede mostrar con esa claridad y ese matiz (si no, el navegador la recorta y el tono cambia), y en el Núcleo
 * se baja a 0,65 para que el azul siga leyéndose como azul noche y no como azul rey.
 */
final class EstiloEstacion
{
    /**
     * Núcleo (D4): el azul noche aclarado o saturado se vuelve azul rey. Su croma baja a 0,65 y sus tonos medios (claridad
     * entre 0,35 y 0,85) se oscurecen 0,08: siguen leyéndose como azul noche. Lo casi blanco y lo casi negro no cambian.
     */
    /**
     * En verano, cada área con un color de playa en vez del suyo (1.4t; pedido del desarrollador: Mien «del sol», el
     * Núcleo «más cercano al color del agua de piscina», «que permita diferenciar es lo más importante»). El ERP, cuyo
     * turquesa quedaría pegado al agua de piscina, pasa a azul mar, y el CRM a coral: entre dos áreas, 30° o más. Sin
     * el ajuste del Núcleo, que solo sirve para que su azul noche no se vuelva azul rey.
     */
    public const array MATIZ_DE_ESTACION = ['verano' => ['personal' => 85.0, 'nucleo' => 195.0, 'erp' => 245.0, 'crm' => 20.0]];

    private const array AJUSTE_DEL_AREA = ['nucleo' => ['croma' => 0.65, 'oscurecer' => 0.08]];

    /**
     * Por estación y tema, cada parte como [claridad, croma] (nulo: la parte no se usa). El texto blanco del verano es
     * [1, 0]. Valores de la vista previa aprobada (pedido del desarrollador, 2026-10-28), aligerados en la 1.4t («lo noto
     * pesado», sobre todo la primavera): menos croma, y en oscuro, cajas algo más claras que el fondo gris carbón. En
     * oscuro, `neon` es el brillo fosforescente del borde, el halo y el ícono.
     */
    private const array PARTES = [
        'primavera' => [
            'claro' => [
                'fondo1' => [0.995, 0.008], 'fondo2' => [0.955, 0.03], 'texto' => [0.30, 0.07],
                'ficha1' => [0.96, 0.03], 'ficha2' => [0.89, 0.06], 'franja1' => [0.86, 0.07], 'franja2' => [0.76, 0.09],
                'sombra' => [0.75, 0.06], 'estrella' => [0.85, 0.12],
            ],
            'oscuro' => [
                'fondo1' => [0.34, 0.04], 'fondo2' => [0.31, 0.05], 'texto' => [0.97, 0.01],
                'ficha1' => [0.50, 0.07], 'ficha2' => [0.42, 0.08], 'franja1' => [0.70, 0.09], 'franja2' => [0.58, 0.11],
                'sombra' => [0.18, 0.04], 'estrella' => [0.95, 0.08], 'neon' => [0.80, 0.15],
            ],
        ],
        'verano' => [
            'claro' => ['fondo1' => [0.97, 0.035], 'fondo2' => [0.90, 0.075], 'texto' => [0.32, 0.08], 'sombra' => [0.66, 0.07], 'estrella' => [0.86, 0.15]],
            'oscuro' => ['fondo1' => [0.48, 0.09], 'fondo2' => [0.34, 0.08], 'texto' => [1.0, 0.0], 'sombra' => [0.15, 0.04], 'estrella' => [0.92, 0.12], 'neon' => [0.80, 0.15]],
        ],
        'otono' => [
            'claro' => [
                'fondo1' => [0.955, 0.02], 'fondo2' => [0.88, 0.035], 'texto' => [0.30, 0.07],
                'icono1' => [0.30, 0.08], 'icono2' => [0.44, 0.10], 'franja1' => [0.60, 0.08], 'franja2' => [0.40, 0.07],
                'sombra' => [0.55, 0.08], 'estrella' => [0.80, 0.14],
            ],
            'oscuro' => [
                'fondo1' => [0.34, 0.03], 'fondo2' => [0.30, 0.035], 'texto' => [0.94, 0.02],
                'icono1' => [0.86, 0.12], 'icono2' => [0.74, 0.14], 'franja1' => [0.62, 0.07], 'franja2' => [0.48, 0.06],
                'sombra' => [0.15, 0.03], 'estrella' => [0.78, 0.12], 'neon' => [0.80, 0.15],
            ],
        ],
        'invierno' => [
            'claro' => [
                'fondo1' => [1.0, 0.0], 'fondo2' => [0.94, 0.03], 'texto' => [0.32, 0.06], 'borde' => [0.85, 0.04],
                'icono1' => [0.40, 0.10], 'icono2' => [0.56, 0.10], 'ficha1' => [1.0, 0.0], 'ficha2' => [0.90, 0.045],
                'sombra' => [0.70, 0.06], 'estrella' => [0.92, 0.08],
            ],
            'oscuro' => [
                'fondo1' => [0.38, 0.02], 'fondo2' => [0.31, 0.03], 'texto' => [0.95, 0.015], 'borde' => [0.50, 0.04],
                'icono1' => [0.86, 0.12], 'icono2' => [0.97, 0.03], 'ficha1' => [0.48, 0.035], 'ficha2' => [0.36, 0.03],
                'sombra' => [0.15, 0.02], 'estrella' => [0.88, 0.08], 'neon' => [0.80, 0.15],
            ],
        ],
    ];

    /** Dónde cae el nombre dentro del degradado del fondo (0 arriba, 1 abajo): se revisa el contraste en estos puntos. */
    public const array PUNTOS_DEL_NOMBRE = [0.3, 0.5, 0.7, 0.9];

    /**
     * Las variables CSS de la estación para cada área, en claro y en oscuro: `--tq-e-<parte>` en `.tq-area`.
     */
    public static function css(Temporada $estacion): string
    {
        $reglas = [];

        foreach (['claro' => '', 'oscuro' => '.dark '] as $tema => $prefijo) {
            foreach (AreaModulo::cases() as $area) {
                $variables = [];

                foreach (array_keys(self::PARTES[$estacion->value][$tema]) as $parte) {
                    $variables[] = "--tq-e-{$parte}: ".vsprintf('oklch(%.3F %.3F %.1F)', self::lch($estacion, $tema, $area, $parte)).';';
                }

                $reglas[] = "{$prefijo}.tq-inicio-cajas[data-tq-estacion=\"{$estacion->value}\"] .tq-area[data-area=\"{$area->value}\"] { ".implode(' ', $variables).' }';
            }
        }

        return implode("\n", $reglas);
    }

    /**
     * Una parte como [L, C, h]: con el matiz del área, su ajuste y la croma recortada a la pantalla; o con el matiz de
     * la estación para esa área, sin ajuste.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function lch(Temporada $estacion, string $tema, AreaModulo $area, string $parte): array
    {
        [$claridad, $croma] = self::PARTES[$estacion->value][$tema][$parte];
        $matiz = self::MATIZ_DE_ESTACION[$estacion->value][$area->value] ?? null;

        if ($matiz !== null) {
            return [$claridad, min($croma, Oklch::cromaMaxima($claridad, $matiz)), $matiz];
        }

        $claridad = self::claridad($area, $claridad);

        return [$claridad, self::croma($area, $tema, $claridad, $croma), ColoresMarca::matizDeArea($area, $tema)];
    }

    /**
     * El peor contraste del nombre contra el fondo, en los puntos del degradado donde puede caer (WCAG AA pide 4,5).
     */
    public static function contrasteDelNombre(Temporada $estacion, string $tema, AreaModulo $area): float
    {
        $texto = Oklch::luminancia(...self::lch($estacion, $tema, $area, 'texto'));
        [$l1, $c1, $matiz] = self::lch($estacion, $tema, $area, 'fondo1');
        [$l2, $c2] = self::lch($estacion, $tema, $area, 'fondo2');
        $peor = 21.0;

        foreach (self::PUNTOS_DEL_NOMBRE as $punto) {
            $peor = min($peor, Oklch::contraste($texto, Oklch::luminancia($l1 + ($l2 - $l1) * $punto, $c1 + ($c2 - $c1) * $punto, $matiz)));
        }

        return $peor;
    }

    /** La claridad ya ajustada al área. */
    private static function claridad(AreaModulo $area, float $claridad): float
    {
        $oscurecer = self::AJUSTE_DEL_AREA[$area->value]['oscurecer'] ?? 0.0;

        return $claridad >= 0.35 && $claridad <= 0.85 ? $claridad - $oscurecer : $claridad;
    }

    /** La croma del área, recortada a la que la pantalla muestra con esa claridad (ya ajustada). */
    private static function croma(AreaModulo $area, string $tema, float $claridad, float $croma): float
    {
        $factor = self::AJUSTE_DEL_AREA[$area->value]['croma'] ?? 1.0;

        return min($croma * $factor, Oklch::cromaMaxima($claridad, ColoresMarca::matizDeArea($area, $tema)));
    }
}
