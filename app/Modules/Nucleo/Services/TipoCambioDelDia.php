<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use Carbon\CarbonImmutable;

/**
 * El tipo de cambio que corresponde a una fecha. `fecha` puede ser anterior a la pedida si la
 * tolerancia lo permite. SUNAT: venta para compras, compra para ventas.
 */
final readonly class TipoCambioDelDia
{
    public function __construct(
        public string $moneda,
        public CarbonImmutable $fecha,
        public string $compra,
        public string $venta,
        public OrigenTipoCambio $origen,
    ) {}
}
