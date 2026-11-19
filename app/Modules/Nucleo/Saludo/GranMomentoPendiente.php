<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

/**
 * El gran momento que le toca ver a una persona (plan 1.4d, D4 y D5): su id, para marcar «Lo vi», y lo que se dibuja.
 */
final readonly class GranMomentoPendiente
{
    public function __construct(
        public int $id,
        public EscenaGranMomento $escena,
    ) {}
}
