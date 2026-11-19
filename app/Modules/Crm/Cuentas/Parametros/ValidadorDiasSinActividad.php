<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Parametros;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `crm.dias_sin_actividad` (plan 2.20, D11): de 7 a 365 días. */
final readonly class ValidadorDiasSinActividad implements ValidadorParametro
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
