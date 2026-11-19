<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Parametros;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Días de validez y «por vencer» de la cotización (plan 2.22, D10): de 1 a 365. */
final readonly class ValidadorDiasCotizacion implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 1 && $valor <= 365 ? null : 'debe estar entre 1 y 365 días.';
    }
}
