<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `avisos.dias_leidos` (plan 1.4y, D4): de 30 a 3650 días. */
final readonly class ValidadorDiasLeidos implements ValidadorParametro
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
