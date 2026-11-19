<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Lo que una plantilla no permite (ADR 0022; plan 2.11): el mensaje dice por qué y, si se puede, qué hacer. Los tipos
 * lo usan también para sus propios rechazos (`conMotivo`), como el de aplicar a un proyecto que ya tiene árbol (D7).
 */
final class PlantillaRechazada extends ErrorDeNegocio
{
    public static function conMotivo(string $motivo): self
    {
        return new self($motivo);
    }

    public static function tipoDesconocido(string $clave): self
    {
        return new self("Esta plantilla es de un tipo que el sistema ya no conoce («{$clave}»): no se puede usar.");
    }

    /**
     * @param  list<string>  $problemas
     */
    public static function contenidoInvalido(array $problemas): self
    {
        return new self('La plantilla no se guarda: '.implode('; ', $problemas).'.');
    }
}
