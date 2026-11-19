<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Models\User;

/** La persona cambia una de sus aplicaciones (plan 1.4p, D7). */
final class CambiarMiAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.cambiar-mi-aplicacion';
    }

    public function nombre(): string
    {
        return 'Cambiar mi aplicación';
    }

    public function campos(): array
    {
        return [self::campoPropia(), ...self::camposDeDatos(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->cambiarPropia($usuario, $datos['aplicacion_id'], self::datos($datos), $datos['version']);

        return null;
    }
}
