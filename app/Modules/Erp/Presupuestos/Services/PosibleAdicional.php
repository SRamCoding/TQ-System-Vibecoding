<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use Carbon\CarbonImmutable;

/**
 * Un gasto aprobado de la PC-99 cuyo motivo es un cambio pedido por el cliente (plan 2.6, D3 y D7). La línea se nombra
 * por su imputación y por su origen (el documento la muestra con su propia numeración, D1).
 */
final readonly class PosibleAdicional
{
    public function __construct(
        public int $imputacionId,
        public int $proyectoId,
        public string $origenType,
        public int $origenId,
        public string $motivo,
        public string $comentario,
        public CarbonImmutable $fecha,
        public string $montoSoles,
    ) {}
}
