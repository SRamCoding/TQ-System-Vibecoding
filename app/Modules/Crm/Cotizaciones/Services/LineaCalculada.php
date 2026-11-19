<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/** Una línea calculada (plan 2.22, D6): montos a 2 decimales; margen en %, nulo si el subtotal es cero. */
final readonly class LineaCalculada
{
    public function __construct(
        public string $bruto,
        public string $descuento,
        public string $subtotal,
        public string $costo,
        public ?string $margenPct,
    ) {}
}
