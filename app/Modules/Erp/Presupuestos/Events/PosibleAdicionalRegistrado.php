<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * C11 (ADR 0020): se aprobó un gasto no presupuestado cuyo motivo es un cambio pedido por el cliente (plan 2.6, D3 y
 * D6). Contratos lo escucha para cobrarlo con una adenda. Se dispara después de confirmar la transacción.
 */
final readonly class PosibleAdicionalRegistrado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $proyectoId,
        /** La imputación de la línea (núcleo): de ahí salen su documento, su motivo y su comentario. */
        public int $imputacionId,
        /** En soles, al céntimo, con el tipo de cambio de la línea. */
        public string $montoSoles,
    ) {}
}
