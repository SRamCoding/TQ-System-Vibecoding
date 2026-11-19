<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use App\Modules\Nucleo\Models\Feriado;

/**
 * Lo que la base rechazaría en un feriado, dicho antes y con un mensaje claro (plan 1.4d, D2).
 */
final class ReglasFeriado
{
    public static function rechazo(Feriado $feriado): ?string
    {
        [$anio, $mes, $dia] = array_map(intval(...), explode('-', $feriado->codigo) + [0, 0, 0]);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $feriado->codigo) !== 1 || ! checkdate($mes, $dia, $anio)) {
            return 'La fecha no existe: escríbela como AAAA-MM-DD.';
        }

        if ($feriado->tipo->nuncaSeTrabaja() && ! $feriado->techquk_no_trabaja) {
            return 'Un '.mb_strtolower($feriado->tipo->etiqueta()).' nunca se trabaja: marca «Techquk no trabaja ese día».';
        }

        return null;
    }
}
