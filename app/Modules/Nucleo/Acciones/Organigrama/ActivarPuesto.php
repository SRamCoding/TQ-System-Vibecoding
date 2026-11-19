<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Vuelve a activar un puesto de una unidad activa. */
final class ActivarPuesto extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'activar-puesto';
    }

    public function nombre(): string
    {
        return 'Activar puesto';
    }

    public function campos(): array
    {
        return [self::campoPuesto(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->activarPuesto($datos['puesto_id'], $datos['version']);

        return null;
    }
}
