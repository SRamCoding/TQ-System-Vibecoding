<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/**
 * Un dato de una tarjeta del Resumen: «Comprometido · S/ 4,200.00». Con desglose, se toca y abre el panel deslizante
 * con sus filas (plan 2.61, D5: todo monto se toca).
 */
final readonly class LineaResumen
{
    /**
     * @param  ?string  $tono  `success`, `warning`, `danger` o null (el de siempre): colores de Filament, nunca un código
     * @param  list<FilaDesglose>  $desglose
     */
    public function __construct(
        public string $etiqueta,
        public string $valor,
        public ?string $tono = null,
        public array $desglose = [],
        public ?string $nota = null,
    ) {}
}
