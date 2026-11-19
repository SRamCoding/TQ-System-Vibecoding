<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un código que no está en el catálogo o que está desactivado. */
final class CatalogoNoEncontrado extends ErrorDeNegocio
{
    public static function codigo(string $catalogo, string $codigo): self
    {
        return new self("«{$codigo}» no está en {$catalogo} o está desactivado.");
    }
}
