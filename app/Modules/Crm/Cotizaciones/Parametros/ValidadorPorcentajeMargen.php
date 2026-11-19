<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Parametros;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Margen objetivo o mínimo de la cotización (plan 2.22, D14): un porcentaje de 0 a 100, con dos decimales. */
final readonly class ValidadorPorcentajeMargen implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    public function rechazo(mixed $valor): ?string
    {
        return is_numeric($valor) && bccomp((string) $valor, '0', 2) >= 0 && bccomp((string) $valor, '100', 2) <= 0
            ? null
            : 'debe estar entre 0 y 100 %.';
    }
}
