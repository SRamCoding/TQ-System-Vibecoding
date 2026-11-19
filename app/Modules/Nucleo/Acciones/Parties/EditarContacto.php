<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/**
 * Cambia los datos del contacto, todos a la vez, como están en el formulario. Ni el cierre del vínculo ni la
 * marca de principal: tienen su acción.
 */
final class EditarContacto extends AccionContacto
{
    protected function operacion(): string
    {
        return 'editar-contacto';
    }

    public function nombre(): string
    {
        return 'Editar contacto';
    }

    public function campos(): array
    {
        return [$this->campoContacto(), ...self::camposDeDatos(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->contactos->editar($datos['contacto_id'], self::datos([...$datos, 'hasta' => null]), $datos['version']);

        return null;
    }
}
