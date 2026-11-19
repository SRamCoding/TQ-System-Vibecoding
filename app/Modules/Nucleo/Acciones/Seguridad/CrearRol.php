<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea un rol vacío: sus permisos se eligen después. Devuelve su id. */
final class CrearRol extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'crear-rol';
    }

    public function nombre(): string
    {
        return 'Crear rol';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [Campo::texto('nombre', 'Nombre', maximo: 125)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->seguridad->crearRol($datos['nombre']);
    }
}
