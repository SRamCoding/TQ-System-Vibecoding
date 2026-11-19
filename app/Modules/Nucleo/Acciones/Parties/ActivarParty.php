<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Vuelve a ofrecerse en los selectores. Mismo permiso que desactivar. */
final class ActivarParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'activar';
    }

    public function nombre(): string
    {
        return 'Activar persona o empresa';
    }

    public function campos(): array
    {
        return [$this->campoParty(), $this->campoVersion()];
    }

    public function permiso(): string
    {
        return 'parties.desactivar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parties->activar($datos['party_id'], $datos['version']);

        return null;
    }
}
