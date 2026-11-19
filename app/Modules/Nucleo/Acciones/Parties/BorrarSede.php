<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Borra una sede que nunca se usó; si ya se usa, se desactiva. */
final class BorrarSede extends AccionSede
{
    protected function operacion(): string
    {
        return 'borrar-sede';
    }

    public function nombre(): string
    {
        return 'Borrar sede';
    }

    public function campos(): array
    {
        return [$this->campoSede(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->sedes->borrar($datos['sede_id'], $datos['version']);

        return null;
    }
}
