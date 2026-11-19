<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use App\Modules\Nucleo\Enums\TipoParametro;

/** Un cambio de parámetro que no se acepta. */
final class ParametroRechazado extends ErrorDeNegocio
{
    public static function noEditable(string $nombre): self
    {
        return new self("El parámetro «{$nombre}» no se puede cambiar desde la aplicación.");
    }

    public static function valorInvalido(string $nombre, TipoParametro $tipo): self
    {
        return new self("El valor no es válido para «{$nombre}»: se espera {$tipo->formatoEsperado()}.");
    }

    public static function noAceptado(string $nombre, string $motivo): self
    {
        return new self("No se cambió «{$nombre}»: {$motivo}");
    }

    public static function sensible(string $nombre): self
    {
        return new self("«{$nombre}» es un parámetro de dinero (cambia un monto, una tasa o a dónde va el dinero): se cambia con «Cambiar parámetro de dinero».");
    }

    public static function noSensible(string $nombre): self
    {
        return new self("«{$nombre}» no es un parámetro de dinero: se cambia con «Cambiar parámetro».");
    }

    public static function descripcionVacia(string $nombre): self
    {
        return new self("La descripción de «{$nombre}» no puede quedar vacía.");
    }
}
