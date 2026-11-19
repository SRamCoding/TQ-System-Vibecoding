<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se quitó un rol a una persona, global o acotado a un ámbito (plan 1.4, D8). Hoy sin oyente: aviso a Gerencia
 * General (1.8/1.10). El ámbito es nulo si el rol era global.
 */
final readonly class RolQuitado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $actorId,
        public int $usuarioId,
        public int $rolId,
        public ?string $ambitoType = null,
        public ?int $ambitoId = null,
    ) {}
}
