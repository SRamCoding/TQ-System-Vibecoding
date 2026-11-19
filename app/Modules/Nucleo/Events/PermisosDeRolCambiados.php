<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cambiaron los permisos de un rol (plan 1.4, D8), con los de antes y los de después, por nombre. Hoy sin
 * oyente: aviso a Gerencia General (1.8/1.10).
 */
final readonly class PermisosDeRolCambiados implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $antes
     * @param  list<string>  $despues
     */
    public function __construct(
        public int $actorId,
        public int $rolId,
        public array $antes,
        public array $despues,
    ) {}
}
