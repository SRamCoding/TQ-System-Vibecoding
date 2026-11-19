<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Borra una party que nunca se usó, con sus roles (D5). Si ya se usó, se rechaza y se sugiere desactivarla. */
final class BorrarParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'borrar';
    }

    public function nombre(): string
    {
        return 'Borrar persona o empresa';
    }

    public function campos(): array
    {
        return [$this->campoParty(), $this->campoVersion()];
    }

    public function permiso(): string
    {
        return 'parties.borrar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parties->borrar($datos['party_id'], $datos['version']);

        return null;
    }
}
