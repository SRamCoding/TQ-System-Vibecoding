<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;

/** Da un rol global a una persona. Si junta permisos incompatibles, pide el control compensatorio. */
final class AsignarRol extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'asignar-rol';
    }

    public function nombre(): string
    {
        return 'Asignar rol';
    }

    public function campos(): array
    {
        return [self::campoUsuario(), self::campoRol(), ...self::camposDeControl()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->asignarRol($datos['usuario_id'], $datos['rol_id'], self::datosControl($datos), $usuario);

        return null;
    }
}
