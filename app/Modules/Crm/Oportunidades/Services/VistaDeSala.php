<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\OrdenDeSala;

/**
 * Cómo ve una persona Ganadas o Perdidas en el tablero de gemas (plan 2.21c, D15): el orden de las cuatro que se ven y,
 * con «Mías», solo las de su cartera (ejecutivo = ella). La cantidad, el total y la tabla del círculo siguen el filtro.
 */
final readonly class VistaDeSala
{
    public function __construct(
        public OrdenDeSala $orden = OrdenDeSala::Recientes,
        /** Con «Mías», el id del usuario; sin filtro, nulo. */
        public ?int $soloDe = null,
    ) {}

    public function mias(): bool
    {
        return $this->soloDe !== null;
    }
}
