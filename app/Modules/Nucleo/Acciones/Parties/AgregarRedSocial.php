<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega una red social a una party o, con `contacto_id`, a uno de sus contactos. Devuelve su id. */
final class AgregarRedSocial extends AccionRedSocial
{
    protected function operacion(): string
    {
        return 'agregar-red-social';
    }

    public function nombre(): string
    {
        return 'Agregar red social';
    }

    public function campos(): array
    {
        return [
            Campo::entero('party_id', 'Persona o empresa')->conReglas('exists:parties,id'),
            Campo::entero('contacto_id', 'Contacto (vacío: de la persona o empresa)')->conReglas('exists:party_contactos,id')->opcional(),
            ...self::camposDeDatos(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->redes->agregar($datos['party_id'], $datos['contacto_id'] ?? null, self::datos($datos));
    }
}
