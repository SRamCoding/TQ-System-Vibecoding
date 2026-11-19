<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Una hora del día como HH:MM, de 00:00 a 23:59 (`interfaz.horario_inicio` y `horario_fin`, plan 1.4e). */
final readonly class ValidadorHora implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        $texto = trim((string) $valor);

        return preg_match('/^(\d):(\d{2})$/', $texto) === 1 ? '0'.$texto : $texto;
    }

    public function rechazo(mixed $valor): ?string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $valor) === 1 ? null : 'debe ser una hora como 08:00 o 19:30.';
    }
}
