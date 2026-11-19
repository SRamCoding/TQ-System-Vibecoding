<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

/** El monto que decide qué reglas de aprobación tocan (plan 1.7, D3): lo declara el documento, en su moneda. */
final readonly class MontoAprobable
{
    /**
     * @param  string  $monto  decimal como texto (`'1500.00'`), nunca float
     * @param  string  $moneda  código ISO (`PEN`, `USD`)
     */
    public function __construct(
        public string $monto,
        public string $moneda,
    ) {}
}
