<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Vuelve a activar una unidad, si la unidad de la que depende está activa. */
final class ActivarUnidad extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'activar-unidad';
    }

    public function nombre(): string
    {
        return 'Activar unidad';
    }

    public function campos(): array
    {
        return [self::campoUnidad(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->activarUnidad($datos['unidad_id'], $datos['version']);

        return null;
    }
}
