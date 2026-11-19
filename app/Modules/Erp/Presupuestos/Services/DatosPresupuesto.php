<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** El encabezado de un presupuesto (plan 2.9). Porcentajes como texto decimal (10 = 10 %). */
final readonly class DatosPresupuesto
{
    public function __construct(
        public string $nombre,
        public int $monedaId,
        public string $gastosGeneralesPct,
        public string $utilidadPct,
        public string $igvPct = '18',
        public bool $usaApu = true,
        public ?int $oportunidadId = null,
    ) {}
}
