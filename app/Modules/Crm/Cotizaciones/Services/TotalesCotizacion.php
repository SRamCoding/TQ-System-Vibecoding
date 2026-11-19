<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/**
 * Los totales de una versión (plan 2.22, D6, D9 y D14). `subtotal` ya lleva los descuentos y va sin IGV; el margen es
 * (subtotal − costo) / subtotal, en %, nulo si el subtotal es cero.
 */
final readonly class TotalesCotizacion
{
    /**
     * @param  list<LineaCalculada>  $lineas  en el mismo orden en que llegaron
     */
    public function __construct(
        public array $lineas,
        public string $subtotal,
        public string $descuento,
        public string $igv,
        public string $total,
        public string $costo,
        public ?string $margenPct,
    ) {}
}
