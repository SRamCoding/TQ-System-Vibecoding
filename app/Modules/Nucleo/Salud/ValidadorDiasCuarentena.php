<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `adjuntos.dias_cuarentena` (plan 1.12, D4): de 7 a 365 días. */
final readonly class ValidadorDiasCuarentena implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 7 && $valor <= 365 ? null : 'debe estar entre 7 y 365 días.';
    }
}
