<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia el nombre de un rol. El de administrador no. */
final class RenombrarRol extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'renombrar-rol';
    }

    public function nombre(): string
    {
        return 'Renombrar rol';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [self::campoRol(), Campo::texto('nombre', 'Nombre', maximo: 125)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->renombrarRol($datos['rol_id'], $datos['nombre']);

        return null;
    }
}
