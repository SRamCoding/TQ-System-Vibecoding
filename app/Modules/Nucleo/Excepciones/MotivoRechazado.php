<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio a una lista de motivos que sus reglas no permiten (plan 1.5, D9). */
final class MotivoRechazado extends ErrorDeNegocio
{
    public static function sinLetras(): self
    {
        return new self('El nombre del motivo necesita letras o números.');
    }

    public static function repetido(string $nombre): self
    {
        return new self("Ya hay un motivo «{$nombre}» en esta lista.");
    }

    public static function otroNoSeDesactiva(): self
    {
        return new self('«Otro» siempre está disponible: no se desactiva.');
    }

    public static function yaEsta(string $nombre, bool $activo): self
    {
        return new self("«{$nombre}» ya está ".($activo ? 'activo' : 'inactivo').'.');
    }
}
