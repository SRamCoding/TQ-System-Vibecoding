<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

/** Cada cuánto se respalda (`respaldos.frecuencia`, plan 1.12, D3; `docs/operaciones/respaldos.md`). */
enum FrecuenciaRespaldo: string
{
    case Diaria = 'diaria';
    case Semanal = 'semanal';

    public function dias(): int
    {
        return match ($this) {
            self::Diaria => 1,
            self::Semanal => 7,
        };
    }
}
