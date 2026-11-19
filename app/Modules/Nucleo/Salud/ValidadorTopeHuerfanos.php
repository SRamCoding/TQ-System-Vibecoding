<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `adjuntos.tope_huerfanos` (plan 1.12, D4): de 1 a 10000 archivos. */
final readonly class ValidadorTopeHuerfanos implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 1 && $valor <= 10000 ? null : 'debe estar entre 1 y 10000 archivos.';
    }
}
