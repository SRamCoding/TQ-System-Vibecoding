<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;

/** Vuelve a activar una cuenta. */
final class ActivarUsuario extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'activar-usuario';
    }

    public function nombre(): string
    {
        return 'Activar usuario';
    }

    public function campos(): array
    {
        return [self::campoUsuario(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->activarUsuario($datos['usuario_id'], $datos['version'], $usuario);

        return null;
    }
}
