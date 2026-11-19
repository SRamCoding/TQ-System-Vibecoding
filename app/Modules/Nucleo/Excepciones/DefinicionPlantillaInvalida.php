<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use LogicException;

/** Error de programación: un tipo de plantilla no cumple las reglas del núcleo (ADR 0022; plan 2.11). */
final class DefinicionPlantillaInvalida extends LogicException
{
    public static function de(string $clave, string $problema): self
    {
        return new self("El tipo de plantilla «{$clave}» no se registra: {$problema}");
    }
}
