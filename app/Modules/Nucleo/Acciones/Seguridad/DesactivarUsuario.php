<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;

/** La cuenta deja de iniciar sesión; sus roles quedan. Nunca la propia ni la del último administrador activo. */
final class DesactivarUsuario extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'desactivar-usuario';
    }

    public function nombre(): string
    {
        return 'Desactivar usuario';
    }

    public function campos(): array
    {
        return [self::campoUsuario(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->desactivarUsuario($datos['usuario_id'], $datos['version'], $usuario);

        return null;
    }
}
