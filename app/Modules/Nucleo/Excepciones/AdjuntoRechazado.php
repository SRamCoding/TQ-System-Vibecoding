<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un archivo que no se acepta, o un cambio que el registro no permite (plan 1.8). No se guarda nada. */
final class AdjuntoRechazado extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
