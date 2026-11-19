<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Parametros;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Días máximos en una etapa (plan 2.21, D2): 0 es «sin máximo»; si no, de 1 a 365. */
final readonly class ValidadorDiasMaximos implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 0 && $valor <= 365 ? null : 'debe estar entre 0 (sin máximo) y 365 días.';
    }
}
