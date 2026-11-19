<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * El umbral de parecido de nombres (`parties.umbral_nombre_parecido`, plan 2.20a, N1): un decimal mayor que 0 y hasta 1,
 * la escala de `similarity()` de pg_trgm. Más bajo, más avisos.
 */
final readonly class ValidadorUmbralParecido implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    public function rechazo(mixed $valor): ?string
    {
        return is_numeric($valor) && (float) $valor > 0 && (float) $valor <= 1 ? null : 'debe estar entre 0.01 y 1.';
    }
}
