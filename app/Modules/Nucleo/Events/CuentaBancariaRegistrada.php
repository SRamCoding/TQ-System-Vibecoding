<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se registró una cuenta bancaria nueva (plan 1.3b, D5). Hoy sin oyente: aquí se enchufan el aviso a Tesorería (1.10) y, si Gerencia lo decide, la
 * aprobación de cuentas antes de recibir pagos (1.7, `docs/decisiones/pendientes.md`). Solo ids: nunca el número.
 */
final readonly class CuentaBancariaRegistrada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $cuentaId,
        public int $partyId,
        public int $usuarioId,
    ) {}
}
