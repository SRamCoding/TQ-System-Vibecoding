<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Latitud y longitud como «-6.77,-79.84» (`interfaz.clima_ubicacion_respaldo`, plan 1.4e2, D5). */
final readonly class ValidadorCoordenadas implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        return (string) preg_replace('/\s+/', '', (string) $valor);
    }

    public function rechazo(mixed $valor): ?string
    {
        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', (string) $valor, $m) !== 1 || abs((float) $m[1]) > 90 || abs((float) $m[2]) > 180) {
            return 'debe ser «latitud,longitud», por ejemplo -6.77,-79.84.';
        }

        return null;
    }
}
