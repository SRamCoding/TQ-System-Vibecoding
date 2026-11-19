<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/**
 * El encabezado y las condiciones de una versión (plan 2.22, D4, D9, D10 y D16). Moneda y días de validez nulos: los de
 * la oportunidad y del parámetro.
 */
final readonly class DatosCotizacion
{
    public function __construct(
        public ?int $contactoId = null,
        public ?int $monedaId = null,
        public ?int $diasValidez = null,
        public ?int $plazoEntregaDias = null,
        public ?int $formaPagoId = null,
        public ?string $formaPagoDetalle = null,
        public ?int $garantiaMeses = null,
        public ?string $lugarEntrega = null,
    ) {}
}
