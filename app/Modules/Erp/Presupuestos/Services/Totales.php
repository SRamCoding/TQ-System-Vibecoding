<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Los montos al pie del presupuesto (plan 2.9), a 2 decimales. */
final readonly class Totales
{
    public function __construct(
        public string $costoDirecto,
        public string $gastosGenerales,
        public string $utilidad,
        public string $igv,
        public string $total,
    ) {}
}
