<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use LogicException;

/** Error de programación: dos declaraciones distintas para el mismo parámetro. */
final class ParametroDuplicado extends LogicException
{
    public static function nombre(string $nombre): self
    {
        return new self("El parámetro «{$nombre}» ya está declarado con otra definición.");
    }

    public static function tipoDistinto(string $nombre, string $enBase, string $declarado): self
    {
        return new self("El parámetro «{$nombre}» es {$enBase} en la base y se declara {$declarado}: cambiar su tipo exige una migración.");
    }
}
