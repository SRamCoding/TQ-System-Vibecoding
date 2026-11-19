<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * Caracteres mínimos del detalle de «Otro» (`motivos.minimo_detalle_otro`, plan 1.5, D9). Al menos 1: la base ya
 * rechaza el detalle en blanco; más de 200 haría de «Otro» un formulario.
 */
final readonly class ValidadorMinimoDetalle implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 1 && $valor <= 200 ? null : 'debe estar entre 1 y 200.';
    }
}
