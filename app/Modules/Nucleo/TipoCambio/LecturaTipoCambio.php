<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\TipoCambio;

use Carbon\CarbonImmutable;

/** Lo que una fuente publicó: el TC de una moneda en una fecha, con compra y venta a 4 decimales. */
final readonly class LecturaTipoCambio
{
    public function __construct(
        public string $moneda,
        public CarbonImmutable $fecha,
        public string $compra,
        public string $venta,
    ) {}
}
