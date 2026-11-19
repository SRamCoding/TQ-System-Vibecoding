<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/**
 * Cambia los datos generales de una party: todos a la vez, como están en el formulario. No el documento ni
 * el tipo de persona (D5), ni las marcas tributarias (D7).
 */
final class EditarParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'editar';
    }

    public function nombre(): string
    {
        return 'Editar persona o empresa';
    }

    public function campos(): array
    {
        return [$this->campoParty(), ...RegistrarParty::camposDeDatos(), $this->campoVersion()];
    }

    public function permiso(): string
    {
        return 'parties.editar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $id = $datos['party_id'];
        $version = $datos['version'];
        unset($datos['party_id'], $datos['version']);

        $this->parties->editar($id, $datos, $version);

        return null;
    }
}
