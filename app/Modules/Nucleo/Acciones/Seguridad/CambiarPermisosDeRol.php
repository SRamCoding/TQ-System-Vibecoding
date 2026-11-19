<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Deja al rol con estos permisos (la lista tiene que venir, aunque sea vacía: omitirla no lo deja sin permisos). Revisa los cruces de todas las personas que lo tienen y pide el control de los nuevos. */
final class CambiarPermisosDeRol extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'cambiar-permisos-de-rol';
    }

    public function nombre(): string
    {
        return 'Cambiar permisos del rol';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [self::campoRol(), Campo::listaDeIds('permisos', 'Permisos', 'permissions', 'name')->opcional()->conReglas('present'), ...self::camposDeControl()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->cambiarPermisosDeRol($datos['rol_id'], $datos['permisos'], self::datosControl($datos), $usuario);

        return null;
    }
}
