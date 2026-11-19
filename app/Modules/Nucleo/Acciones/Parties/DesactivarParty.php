<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Deja de ofrecerse en los selectores; lo ya registrado la conserva. */
final class DesactivarParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'desactivar';
    }

    public function nombre(): string
    {
        return 'Desactivar persona o empresa';
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
        $this->parties->desactivar($datos['party_id'], $datos['version']);

        return null;
    }
}
