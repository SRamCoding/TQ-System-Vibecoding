<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Agrega un contacto a una party. Si llega como principal, el que lo era deja de serlo. Con `hasta` se registra
 * un vínculo que ya terminó. Devuelve el id del contacto.
 */
final class AgregarContacto extends AccionContacto
{
    protected function operacion(): string
    {
        return 'agregar-contacto';
    }

    public function nombre(): string
    {
        return 'Agregar contacto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('party_id', 'Persona o empresa')->conReglas('exists:parties,id'),
            ...self::camposDeDatos(),
            Campo::booleano('es_principal', 'Contacto principal')->opcional(),
            Campo::fecha('hasta', 'Hasta (si ya no está)')->opcional(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->contactos->agregar($datos['party_id'], self::datos($datos, (bool) ($datos['es_principal'] ?? false)));
    }
}
