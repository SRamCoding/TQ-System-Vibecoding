<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Seguridad\DatosUsuario;

/** Da una cuenta a una persona (ficha de DNI, CE o pasaporte), con su correo y, si se sabe, su puesto. Devuelve su id. */
final class RegistrarUsuario extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'registrar-usuario';
    }

    public function nombre(): string
    {
        return 'Registrar usuario';
    }

    public function campos(): array
    {
        return [
            Campo::entero('party_id', 'Persona')->conReglas('exists:parties,id'),
            Campo::texto('email', 'Correo', maximo: 150),
            Campo::entero('puesto_id', 'Puesto')->conReglas('exists:puestos,id')->opcional(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->seguridad->registrarUsuario(new DatosUsuario($datos['party_id'], $datos['email'], $datos['puesto_id'] ?? null));
    }
}
