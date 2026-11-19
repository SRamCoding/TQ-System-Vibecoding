<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * La cotización aceptada de una oportunidad, como la da Cotizaciones (plan 2.22, D12; C3): lo que viaja en
 * `OportunidadGanada` sin reescribirse. El total va con IGV, como texto decimal.
 */
final readonly class CotizacionAceptada
{
    public function __construct(
        public int $id,
        /** «COT-2026-0190 v1» */
        public string $identificador,
        public string $total,
        public string $moneda,
        /** «45 días calendario», o nulo */
        public ?string $plazo,
        /** «Crédito 30 días · 50 % adelanto», o nulo */
        public ?string $formaPago,
        /** «24 meses», o nulo */
        public ?string $garantia,
        public ?int $presupuestoId,
    ) {}
}
