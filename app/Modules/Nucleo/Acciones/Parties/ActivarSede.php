<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Vuelve a ofrecerse, si su nombre y su anexo siguen libres. */
final class ActivarSede extends AccionSede
{
    protected function operacion(): string
    {
        return 'activar-sede';
    }

    public function nombre(): string
    {
        return 'Activar sede';
    }

    public function campos(): array
    {
        return [$this->campoSede(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->sedes->activar($datos['sede_id'], $datos['version']);

        return null;
    }
}
