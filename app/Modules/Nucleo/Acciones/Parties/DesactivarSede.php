<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Deja de ofrecerse; libera su nombre y su anexo. */
final class DesactivarSede extends AccionSede
{
    protected function operacion(): string
    {
        return 'desactivar-sede';
    }

    public function nombre(): string
    {
        return 'Desactivar sede';
    }

    public function campos(): array
    {
        return [$this->campoSede(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->sedes->desactivar($datos['sede_id'], $datos['version']);

        return null;
    }
}
