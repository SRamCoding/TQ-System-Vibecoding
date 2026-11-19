<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use LogicException;

/** Error de programación: dos clases registran la misma clave de acción. */
final class AccionDuplicada extends LogicException
{
    public static function clave(string $clave, string $existente, string $nueva): self
    {
        return new self("La clave de acción «{$clave}» ya la usa {$existente}; {$nueva} necesita otra.");
    }
}
