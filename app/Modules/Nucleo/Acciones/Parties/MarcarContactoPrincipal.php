<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Lo hace el contacto principal de su party; el que lo era deja de serlo (D2). */
final class MarcarContactoPrincipal extends AccionContacto
{
    protected function operacion(): string
    {
        return 'marcar-contacto-principal';
    }

    public function nombre(): string
    {
        return 'Marcar como contacto principal';
    }

    public function campos(): array
    {
        return [$this->campoContacto(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->contactos->marcarPrincipal($datos['contacto_id'], $datos['version']);

        return null;
    }
}
