<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use Illuminate\Support\Str;

/**
 * La identidad de la sesión para el saludo (plan 1.4e, D3): cambia al iniciar sesión, así cada sesión trae otro saludo,
 * y se queda mientras se navega. No es un secreto ni se guarda en la base: solo vive en la sesión.
 */
final class SemillaSesion
{
    private const string SESION = 'nucleo.saludo.semilla';

    public static function actual(): string
    {
        $semilla = session()->get(self::SESION);

        if (! is_string($semilla) || $semilla === '') {
            $semilla = self::renovar();
        }

        return $semilla;
    }

    /** Al iniciar sesión (evento `Login`): otra semilla, otro saludo. */
    public static function renovar(): string
    {
        $semilla = Str::random(16);
        session()->put(self::SESION, $semilla);

        return $semilla;
    }
}
