<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Models\User;

/** La persona suma una aplicación a su panel (plan 1.4p, D7). Devuelve su id. */
final class AgregarMiAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.agregar-mi-aplicacion';
    }

    public function nombre(): string
    {
        return 'Agregar mi aplicación';
    }

    public function campos(): array
    {
        return self::camposDeDatos();
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->aplicaciones->agregarPropia($usuario, self::datos($datos));
    }
}
