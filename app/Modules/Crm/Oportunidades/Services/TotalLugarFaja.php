<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;

/**
 * Lo que dicen la viñeta, el rótulo y el terminal de un lugar de la faja (plan 2.21b; pedido B5): cuántas hay y su
 * total en moneda local al T.C. de compra de hoy, como el embudo. Lo que no tiene T.C. no suma y se cuenta aparte.
 */
final readonly class TotalLugarFaja
{
    public function __construct(
        public LugarFaja $lugar,
        public int $cantidad,
        public string $totalEnSoles,
        public int $sinTipoCambio,
    ) {}
}
