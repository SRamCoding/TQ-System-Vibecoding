<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia el nombre del puesto. No cambia de unidad. */
final class EditarPuesto extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'editar-puesto';
    }

    public function nombre(): string
    {
        return 'Editar puesto';
    }

    public function campos(): array
    {
        return [self::campoPuesto(), Campo::texto('nombre', 'Nombre', maximo: 120), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->editarPuesto($datos['puesto_id'], $datos['nombre'], $datos['version']);

        return null;
    }
}
