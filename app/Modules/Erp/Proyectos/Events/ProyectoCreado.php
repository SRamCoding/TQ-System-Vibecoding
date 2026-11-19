<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** C2 (ADR 0020): nació un proyecto, «en preparación». Se dispara después de confirmar la transacción. */
final readonly class ProyectoCreado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $proyectoId,
        public string $codigo,
        public ?int $contratoId,
    ) {}
}
