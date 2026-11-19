<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

/**
 * El dibujo de una oportunidad sin foto (plan 2.21d, D1): el del prototipo aprobado, por su **línea de solución**: un
 * degradado de dos colores con dos círculos de luz y el motivo de la línea en blanco. Las líneas que el prototipo no
 * dibuja (o que agregue el negocio) toman un motivo genérico y una paleta fija por su id. Colores de dibujo, no de la
 * interfaz.
 */
final class DibujoDeLinea
{
    /** Por código de la línea (catálogo `lineas_solucion`): [claro, oscuro, motivo]. */
    public const array LINEAS = [
        'VIDEOVIGILANCIA' => ['#C7D2FE', '#4338CA', 'cam'],
        'REDES' => ['#BAE6FD', '#0369A1', 'red'],
        'CONTROL_ACCESOS' => ['#BBF7D0', '#15803D', 'acc'],
        'DETECCION_INCENDIOS' => ['#FECACA', '#B91C1C', 'fue'],
        'ENLACES' => ['#A5F3FC', '#0E7490', 'ant'],
        'DATACENTER' => ['#E9D5FF', '#7E22CE', 'dc'],
    ];

    /** Para las líneas sin dibujo propio. */
    private const array PALETAS = [['#FDE68A', '#B45309'], ['#FBCFE8', '#9D174D'], ['#C7D2FE', '#3730A3'], ['#BBF7D0', '#166534']];

    public static function svg(int $oportunidadId, string $codigoLinea, int $lineaId): string
    {
        [$claro, $oscuro, $motivo] = self::LINEAS[$codigoLinea] ?? [...self::PALETAS[$lineaId % count(self::PALETAS)], 'gen'];
        $dc = '';

        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $dc .= sprintf('<rect x="%d" y="%d" width="18" height="4"/>', 155 + $i * 36, 38 + $j * 16);
            }
        }

        $dibujo = match ($motivo) {
            'cam' => "<g transform=\"translate(168,40)\" fill=\"#fff\"><rect width=\"70\" height=\"32\" rx=\"7\"/><rect x=\"70\" y=\"8\" width=\"18\" height=\"16\" rx=\"4\"/><rect x=\"28\" y=\"32\" width=\"10\" height=\"22\"/><circle cx=\"18\" cy=\"16\" r=\"7\" fill=\"{$oscuro}\"/></g>",
            'red' => '<g stroke="#fff" stroke-width="4" fill="none"><path d="M120 50h160M120 70h160M120 90h160"/></g><g fill="#fff"><circle cx="120" cy="50" r="6"/><circle cx="280" cy="70" r="6"/><circle cx="200" cy="90" r="6"/></g>',
            'acc' => "<g fill=\"#fff\"><rect x=\"178\" y=\"28\" width=\"44\" height=\"72\" rx=\"8\"/><circle cx=\"200\" cy=\"56\" r=\"10\" fill=\"{$oscuro}\"/><rect x=\"188\" y=\"78\" width=\"24\" height=\"5\" rx=\"2\" fill=\"{$oscuro}\"/></g>",
            'fue' => '<path d="M200 22c18 24 28 36 28 54a28 28 0 0 1-56 0c0-14 8-22 13-29 3 10 8 13 10 13-3-16 0-26 5-38z" fill="#fff"/>',
            'ant' => '<g stroke="#fff" stroke-width="4" fill="none"><path d="M200 108V44M184 108l16-64 16 64M178 40a32 32 0 0 1 44 0M166 28a48 48 0 0 1 68 0"/></g>',
            'dc' => '<g fill="#fff"><rect x="150" y="30" width="28" height="76" rx="3"/><rect x="186" y="30" width="28" height="76" rx="3"/><rect x="222" y="30" width="28" height="76" rx="3"/></g>'."<g fill=\"{$oscuro}\">{$dc}</g>",
            // Genérico: tres capas apiladas (una solución armada por partes), distinto de los seis de arriba.
            default => '<g fill="#fff"><path d="M200 30l52 22-52 22-52-22z"/><path d="M148 70l52 22 52-22v10l-52 22-52-22z" opacity=".85"/><path d="M148 88l52 22 52-22v10l-52 22-52-22z" opacity=".7"/></g>',
        };

        return '<svg viewBox="0 0 400 130" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            ."<defs><linearGradient id=\"tq-dl-{$oportunidadId}\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\"><stop offset=\"0\" stop-color=\"{$claro}\"/><stop offset=\"1\" stop-color=\"{$oscuro}\"/></linearGradient></defs>"
            ."<rect width=\"400\" height=\"130\" fill=\"url(#tq-dl-{$oportunidadId})\"/>"
            .'<circle cx="340" cy="20" r="70" fill="#fff" opacity=".12"/><circle cx="40" cy="130" r="60" fill="#fff" opacity=".08"/>'
            .$dibujo.'</svg>';
    }
}
