<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia el puesto del usuario en el organigrama, o lo deja sin puesto. */
final class CambiarPuestoUsuario extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'cambiar-puesto-usuario';
    }

    public function nombre(): string
    {
        return 'Cambiar puesto';
    }

    public function campos(): array
    {
        return [self::campoUsuario(), Campo::entero('puesto_id', 'Puesto')->conReglas('exists:puestos,id')->opcional()->conReglas('present'), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->cambiarPuesto($datos['usuario_id'], $datos['puesto_id'] ?? null, $datos['version'], $usuario);

        return null;
    }
}
