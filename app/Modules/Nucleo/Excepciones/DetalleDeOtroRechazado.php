<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** El detalle de «Otro» de un catálogo (plan 2.0, D5): falta, es muy corto o sobra. */
final class DetalleDeOtroRechazado extends ErrorDeNegocio
{
    public static function falta(string $que, int $minimo): self
    {
        return new self("Elegiste «Otro» en {$que}: escribe cuál es (al menos {$minimo} caracteres).");
    }

    public static function sobra(string $que): self
    {
        return new self("El detalle de {$que} va solo con «Otro».");
    }
}
