<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Los gastos aprobados de la PC-99 de un proyecto con un motivo (plan 2.6, D7). Montos como texto decimal, en soles. */
final readonly class GastoPorMotivo
{
    public function __construct(
        public int $proyectoId,
        public int $motivoId,
        public string $motivo,
        /** Si el motivo es un cambio pedido por el cliente: un posible adicional (D3). */
        public bool $esCambioCliente,
        public int $cantidad,
        public string $totalSoles,
    ) {}
}
