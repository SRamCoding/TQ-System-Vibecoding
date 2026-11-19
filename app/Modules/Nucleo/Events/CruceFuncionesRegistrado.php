<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una persona juntó dos permisos incompatibles (plan 1.4, D9). `conControl` falso: quedó pendiente de control
 * (se agregó el par al catálogo después de darle los permisos). Hoy sin oyente: aviso a Gerencia General (1.10).
 */
final readonly class CruceFuncionesRegistrado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $cruceId,
        public int $usuarioId,
        public int $incompatibilidadId,
        public bool $conControl,
    ) {}
}
