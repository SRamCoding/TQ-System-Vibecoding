<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use LogicException;

/** Error de programación: la definición de estados de un documento no cumple las reglas del núcleo (plan 1.5). */
final class DefinicionDocumentoInvalida extends LogicException
{
    public static function de(string $clave, string $problema): self
    {
        return new self("La definición de «{$clave}» no se registra: {$problema}");
    }
}
