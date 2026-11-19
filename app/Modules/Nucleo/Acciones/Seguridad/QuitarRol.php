<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;

/** Quita un rol global a una persona y cierra los cruces que deja de juntar. */
final class QuitarRol extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'quitar-rol';
    }

    public function nombre(): string
    {
        return 'Quitar rol';
    }

    public function campos(): array
    {
        return [self::campoUsuario(), self::campoRol()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->quitarRol($datos['usuario_id'], $datos['rol_id'], $usuario);

        return null;
    }
}
