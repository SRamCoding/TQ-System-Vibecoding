<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La persona dejó de juntar los dos permisos de un par, o el par se desactivó: el cruce se cerró (plan 1.4, D9).
 */
final readonly class CruceFuncionesCerrado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $cruceId,
        public int $usuarioId,
        public int $incompatibilidadId,
    ) {}
}
