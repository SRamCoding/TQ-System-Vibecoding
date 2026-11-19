<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** La foto de perfil no se puede guardar (plan 1.4o, D6 y D7). */
final class FotoRechazada extends ErrorDeNegocio
{
    public static function noEsImagen(): self
    {
        return new self('La foto debe ser una imagen JPG, PNG o WebP.');
    }

    /** Plan 1.4o, D6: el navegador de una computadora no lee HEIC; el iPhone la convierte solo al subirla desde él. */
    public static function heic(): self
    {
        return new self('Esa foto está en HEIC, el formato del iPhone. Súbela desde el mismo iPhone (la convierte sola) o guárdala antes como JPG.');
    }

    public static function muyGrande(int $topeMb): self
    {
        return new self("La foto pesa más de {$topeMb} MB.");
    }
}
