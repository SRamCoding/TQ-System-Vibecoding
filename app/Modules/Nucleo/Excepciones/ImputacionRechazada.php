<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un destino o un monto que las reglas de la imputación no permiten (plan 1.9; regla 3). No se escribe nada. */
final class ImputacionRechazada extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
