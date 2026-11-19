<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Cambia los datos de la sede, todos a la vez, como están en el formulario. */
final class EditarSede extends AccionSede
{
    protected function operacion(): string
    {
        return 'editar-sede';
    }

    public function nombre(): string
    {
        return 'Editar sede';
    }

    public function campos(): array
    {
        return [$this->campoSede(), ...self::camposDeDatos(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->sedes->editar($datos['sede_id'], self::datos($datos), $datos['version']);

        return null;
    }
}
