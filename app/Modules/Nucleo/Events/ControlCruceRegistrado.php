<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se registró o renovó el control compensatorio de un cruce de funciones (plan 1.4, D9).
 */
final readonly class ControlCruceRegistrado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $controlId,
        public int $cruceId,
        public int $responsableId,
    ) {}
}
