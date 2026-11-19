<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Aviso de que una oportunidad ganada se anuló (C3 ampliada; plan 2.21, D1). Lo esencial (contrato, proyecto, marca de
 * cliente) ya se hizo dentro de la transacción: esto es solo para quien quiera enterarse.
 */
final readonly class OportunidadGanadaAnulada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $oportunidadId,
        public int $cuentaId,
        public string $motivo,
    ) {}
}
