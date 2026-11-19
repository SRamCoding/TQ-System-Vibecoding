<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `salud.dias_historial` (plan 1.12, D1): de 30 a 3650 días de corridas de respaldos y huérfanos. */
final readonly class ValidadorDiasHistorial implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 30 && $valor <= 3650 ? null : 'debe estar entre 30 y 3650 días.';
    }
}
