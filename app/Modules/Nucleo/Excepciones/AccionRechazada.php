<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * El usuario no puede ejecutar la acción. El mensaje se muestra tal cual.
 */
final class AccionRechazada extends ErrorDeNegocio
{
    public static function usuarioDeSistema(): self
    {
        return new self('El usuario de sistema no ejecuta acciones de usuario.');
    }

    public static function usuarioInactivo(): self
    {
        return new self('Tu usuario está dado de baja.');
    }

    public static function sinPermiso(string $accion): self
    {
        return new self("No tienes permiso para «{$accion}».");
    }
}
