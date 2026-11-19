<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un tipo de cambio que no se puede registrar. */
final class TipoCambioInvalido extends ErrorDeNegocio
{
    public static function monedaLocal(string $moneda): self
    {
        return new self("{$moneda} es la moneda local: no lleva tipo de cambio.");
    }

    public static function valor(string $campo): self
    {
        return new self("El tipo de cambio de {$campo} debe ser mayor que cero y tener hasta cuatro decimales.");
    }
}
