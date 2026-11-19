<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Desactiva una unidad sin unidades ni puestos activos. */
final class DesactivarUnidad extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'desactivar-unidad';
    }

    public function nombre(): string
    {
        return 'Desactivar unidad';
    }

    public function campos(): array
    {
        return [self::campoUnidad(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->desactivarUnidad($datos['unidad_id'], $datos['version']);

        return null;
    }
}
