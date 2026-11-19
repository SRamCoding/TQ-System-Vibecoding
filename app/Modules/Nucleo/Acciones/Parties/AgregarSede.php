<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega una sede (sucursal, almacén, punto de entrega) a una party. Devuelve su id. */
final class AgregarSede extends AccionSede
{
    protected function operacion(): string
    {
        return 'agregar-sede';
    }

    public function nombre(): string
    {
        return 'Agregar sede';
    }

    public function campos(): array
    {
        return [Campo::entero('party_id', 'Persona o empresa')->conReglas('exists:parties,id'), ...self::camposDeDatos()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->sedes->agregar($datos['party_id'], self::datos($datos));
    }
}
