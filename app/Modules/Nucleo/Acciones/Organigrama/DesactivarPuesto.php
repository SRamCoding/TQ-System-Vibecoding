<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Desactiva un puesto sin ocupantes activos que no sea jefatura. */
final class DesactivarPuesto extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'desactivar-puesto';
    }

    public function nombre(): string
    {
        return 'Desactivar puesto';
    }

    public function campos(): array
    {
        return [self::campoPuesto(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->desactivarPuesto($datos['puesto_id'], $datos['version']);

        return null;
    }
}
