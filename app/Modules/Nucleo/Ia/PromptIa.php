<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/** Una versión del prompt fijo de un proceso (plan 1.11, D10), con la fecha y el motivo del cambio. */
final readonly class PromptIa
{
    public function __construct(
        public int $version,
        public string $fecha,
        public string $motivo,
        public string $texto,
    ) {}
}
