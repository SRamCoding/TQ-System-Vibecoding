<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dio un rol a una persona, global o acotado a un ámbito (plan 1.4, D8). Hoy sin oyente: aquí se enchufa el
 * aviso a Gerencia General (1.8/1.10). `actorId` es quien lo dio; el ámbito es nulo si el rol es global.
 */
final readonly class RolAsignado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $actorId,
        public int $usuarioId,
        public int $rolId,
        public ?string $ambitoType = null,
        public ?int $ambitoId = null,
        public ?string $desde = null,
        public ?string $hasta = null,
    ) {}
}
