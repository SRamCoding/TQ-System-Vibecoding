<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use LogicException;

/** Error de programación: se pidió una acción que ningún módulo registró. */
final class AccionNoRegistrada extends LogicException
{
    public static function clave(string $clave): self
    {
        return new self("No hay ninguna acción registrada con la clave «{$clave}».");
    }
}
