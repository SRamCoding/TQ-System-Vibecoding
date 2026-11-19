<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament\Portada;

/**
 * El dibujo de un proyecto sin foto (plan 2.61, D12), el del prototipo aprobado: degradado diagonal de dos colores,
 * edificios del color oscuro al 55 %, ventanas blancas al 35 % y una franja abajo, en 16:9. Cada proyecto toma una de
 * las ocho paletas por su id (la misma siempre). El motivo del centro va por tipología cuando exista (D8); mientras,
 * un chip genérico. Colores de dibujo, no de la interfaz: por eso van aquí y no en la paleta del sistema.
 */
final class DibujoProyecto
{
    /** Claro → oscuro, del prototipo. */
    public const array PALETAS = [
        ['#A5F3FC', '#155E75'], ['#7DD3FC', '#0369A1'], ['#C7D2FE', '#3730A3'], ['#E9D5FF', '#6D28D9'],
        ['#FBCFE8', '#9D174D'], ['#FECACA', '#B91C1C'], ['#FDE68A', '#B45309'], ['#BBF7D0', '#15803D'],
    ];

    /**
     * El chip: un cuadrado de esquinas redondeadas con patitas a los lados, en el blanco al 90 % y el lugar de los
     * motivos del prototipo; distinto de los seis de tipología.
     */
    public const string CHIP = '<g fill="#fff" opacity=".9"><rect x="261" y="52" width="44" height="44" rx="7"/>'
        .'<g stroke="#fff" stroke-width="4" stroke-linecap="round"><path d="M271 46v-6M283 46v-6M295 46v-6M271 102v6M283 102v6M295 102v6M255 62h-6M255 74h-6M255 86h-6M311 62h6M311 74h6M311 86h6"/></g></g>';

    /** @return array{0: string, 1: string} */
    public static function paleta(int $proyectoId): array
    {
        return self::PALETAS[$proyectoId % count(self::PALETAS)];
    }

    /** El SVG, con un id de degradado propio (hay varios en la página). `$motivo`: el de la tipología, cuando exista. */
    public static function svg(int $proyectoId, ?string $motivo = null): string
    {
        [$claro, $oscuro] = self::paleta($proyectoId);
        $ventanas = '';

        for ($i = 0; $i < 12; $i++) {
            $ventanas .= sprintf('<rect x="%d" y="%d" width="10" height="12"/>', 105 + ($i % 4) * 20, 108 + intdiv($i, 4) * 24);
        }

        return '<svg viewBox="0 0 400 225" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            ."<defs><linearGradient id=\"tq-dp-{$proyectoId}\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\"><stop offset=\"0\" stop-color=\"{$claro}\"/><stop offset=\"1\" stop-color=\"{$oscuro}\"/></linearGradient></defs>"
            ."<rect width=\"400\" height=\"225\" fill=\"url(#tq-dp-{$proyectoId})\"/>"
            ."<g fill=\"{$oscuro}\" opacity=\".55\"><rect x=\"20\" y=\"120\" width=\"70\" height=\"105\"/><rect x=\"95\" y=\"95\" width=\"90\" height=\"130\"/><rect x=\"190\" y=\"135\" width=\"60\" height=\"90\"/><rect x=\"330\" y=\"110\" width=\"70\" height=\"115\"/></g>"
            ."<g fill=\"#fff\" opacity=\".35\">{$ventanas}</g>"
            ."<rect y=\"205\" width=\"400\" height=\"20\" fill=\"{$oscuro}\" opacity=\".8\"/>"
            .($motivo ?? self::CHIP)
            .'</svg>';
    }
}
