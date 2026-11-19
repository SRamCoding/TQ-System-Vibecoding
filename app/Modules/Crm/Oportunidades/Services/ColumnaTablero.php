<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;

/**
 * Una columna del tablero de gemas (plan 2.21c, D2): una etapa abierta o una de las dos salas (Ganadas, Perdidas), con
 * las que se ven, cuántas hay y su total en moneda local al T.C. de compra de hoy (lo que no tiene T.C. se cuenta pero no
 * suma). La probabilidad y el ponderado, solo en una etapa abierta. Montos como texto decimal.
 */
final readonly class ColumnaTablero
{
    /**
     * @param  list<FilaFaja>  $visibles  hasta `TableroDeGemas::VISIBLES`, en su orden
     */
    public function __construct(
        public LugarFaja $lugar,
        public array $visibles,
        public int $cantidad,
        public string $totalEnSoles,
        public int $sinTipoCambio,
        /** El porcentaje de la etapa (parámetro); nulo en una sala. */
        public ?int $probabilidad,
        /** El total por la probabilidad; nulo en una sala. */
        public ?string $ponderadoEnSoles,
        /** En una sala, su orden y su «Mías» (D15); nula en una etapa abierta. */
        public ?VistaDeSala $vista = null,
    ) {}

    /** Las que no se ven: con más de cuatro, la línea «Se ven 4 de N». */
    public function ocultas(): int
    {
        return $this->cantidad - count($this->visibles);
    }
}
