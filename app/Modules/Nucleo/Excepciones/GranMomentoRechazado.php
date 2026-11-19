<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Una marca de «Lo vi» que no procede (plan 1.4d, D4).
 */
final class GranMomentoRechazado extends ErrorDeNegocio
{
    public static function noVigente(): self
    {
        return new self('Ese gran momento no está vigente para ti.');
    }
}
