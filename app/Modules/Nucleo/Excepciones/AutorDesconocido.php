<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use RuntimeException;

/**
 * Se intentó guardar un cambio sin saber quién lo hace (M4). Nunca se guarda una fila sin autor.
 */
final class AutorDesconocido extends RuntimeException
{
    public static function crear(): self
    {
        return new self('No se puede guardar el cambio: no se sabe quién lo hace. Inicia sesión e inténtalo de nuevo.');
    }
}
