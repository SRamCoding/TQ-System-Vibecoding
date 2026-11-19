<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Cambia la red y el enlace, como están en el formulario. */
final class CambiarRedSocial extends AccionRedSocial
{
    protected function operacion(): string
    {
        return 'cambiar-red-social';
    }

    public function nombre(): string
    {
        return 'Cambiar red social';
    }

    public function campos(): array
    {
        return [$this->campoRed(), ...self::camposDeDatos(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->redes->cambiar($datos['red_id'], self::datos($datos), $datos['version']);

        return null;
    }
}
