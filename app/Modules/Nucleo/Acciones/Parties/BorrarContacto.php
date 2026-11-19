<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Borra un contacto que nunca se usó; si ya se usa, se cierra su vínculo (D6). */
final class BorrarContacto extends AccionContacto
{
    protected function operacion(): string
    {
        return 'borrar-contacto';
    }

    public function nombre(): string
    {
        return 'Borrar contacto';
    }

    public function campos(): array
    {
        return [$this->campoContacto(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->contactos->borrar($datos['contacto_id'], $datos['version']);

        return null;
    }
}
