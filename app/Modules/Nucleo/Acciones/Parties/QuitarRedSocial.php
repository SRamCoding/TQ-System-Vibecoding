<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Quita una red social de la party o del contacto. */
final class QuitarRedSocial extends AccionRedSocial
{
    protected function operacion(): string
    {
        return 'quitar-red-social';
    }

    public function nombre(): string
    {
        return 'Quitar red social';
    }

    public function campos(): array
    {
        return [$this->campoRed(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->redes->quitar($datos['red_id'], $datos['version']);

        return null;
    }
}
