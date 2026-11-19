<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Se pidió un parámetro que no existe o se leyó con otro tipo: falta declararlo o sembrarlo. */
final class ParametroNoDisponible extends ErrorDeNegocio
{
    public static function noExiste(string $nombre): self
    {
        return new self("No existe el parámetro «{$nombre}». Revisa que su módulo lo declare y que se haya corrido el seeder del núcleo.");
    }

    public static function deOtroTipo(string $nombre, string $tipo, string $pedido): self
    {
        return new self("El parámetro «{$nombre}» es {$tipo} y se pidió como {$pedido}.");
    }
}
