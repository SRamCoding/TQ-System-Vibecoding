<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/**
 * Lo que el cálculo necesita de una línea (plan 2.22, D6), como texto decimal: recibe la línea, no la tabla, para que
 * las líneas opcionales o el IGV por línea (diferidos) se sumen sin cambiarlo.
 */
final readonly class LineaParaCalculo
{
    public function __construct(
        public string $cantidad,
        public string $precioUnitario,
        public string $descuentoPct,
        public string $costoUnitario,
    ) {}
}
