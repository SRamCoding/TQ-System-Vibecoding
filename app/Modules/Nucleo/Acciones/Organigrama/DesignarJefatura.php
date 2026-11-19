<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Elige el puesto de jefatura de una unidad (de la misma unidad), o lo deja sin definir. */
final class DesignarJefatura extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'designar-jefatura';
    }

    public function nombre(): string
    {
        return 'Designar jefatura';
    }

    public function campos(): array
    {
        return [self::campoUnidad(), self::campoPuesto()->opcional()->conReglas('present'), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->designarJefatura($datos['unidad_id'], $datos['puesto_id'] ?? null, $datos['version']);

        return null;
    }
}
