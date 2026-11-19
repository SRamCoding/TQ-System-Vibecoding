<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * Los días después del día clave en que todavía sale el gran momento de una fecha (plan 1.4d, D3): de 0 a 30.
 */
final readonly class ValidadorPlazoGranMomento implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 0 && $valor <= 30 ? null : 'debe estar entre 0 y 30 días.';
    }
}
