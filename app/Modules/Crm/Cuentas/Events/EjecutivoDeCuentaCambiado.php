<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una cuenta cambió de ejecutivo (plan 2.20, D7 y D12), ya confirmado. Oportunidades (CRM-02) lo escuchará para pasar
 * también sus oportunidades abiertas si se pidió; las cerradas nunca cambian de ejecutivo.
 */
final readonly class EjecutivoDeCuentaCambiado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $cuentaId,
        public int $ejecutivoAnteriorUserId,
        public int $ejecutivoNuevoUserId,
        public bool $incluyeOportunidadesAbiertas,
    ) {}
}
