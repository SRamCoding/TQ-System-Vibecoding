<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * La fila de cifras de arriba de las vistas de Oportunidades (plan 2.21c, D1), cada una con su cantidad, total y
 * ponderado en moneda local (`EmbudoEnSoles`). Solo abiertas: las anuladas y las cerradas no cuentan.
 */
final readonly class CifrasTablero
{
    public function __construct(
        /** El embudo abierto; su ponderado es el del embudo. */
        public EmbudoEnSoles $embudo,
        /** Las que cierran este mes (cierre estimado en el mes en curso). */
        public EmbudoEnSoles $esteMes,
        /** Las que pasan los días máximos de su etapa. */
        public EmbudoEnSoles $atascadas,
        /** Cierre estimado vencido: antes de hoy y todavía abiertas. */
        public EmbudoEnSoles $vencidas,
    ) {}
}
