<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cierra el vínculo del contacto: deja de ser principal y sale de sus sedes (D1, D2 y D4). */
final class CerrarVinculoContacto extends AccionContacto
{
    protected function operacion(): string
    {
        return 'cerrar-vinculo-contacto';
    }

    public function nombre(): string
    {
        return 'Cerrar vínculo';
    }

    public function campos(): array
    {
        return [$this->campoContacto(), Campo::fecha('hasta', 'Último día en la institución'), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->contactos->cerrarVinculo($datos['contacto_id'], $datos['hasta'], $datos['version']);

        return null;
    }
}
