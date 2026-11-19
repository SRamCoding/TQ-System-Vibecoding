<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * Un porcentaje entero de 0 a 100 (`interfaz.saludo_porcentaje_espanol`, plan 1.4b, D1b).
 */
final readonly class ValidadorPorcentaje implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 0 && $valor <= 100 ? null : 'debe estar entre 0 y 100.';
    }
}
