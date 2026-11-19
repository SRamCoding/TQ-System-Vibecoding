<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea un puesto dentro de una unidad activa. Devuelve su id. */
final class CrearPuesto extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'crear-puesto';
    }

    public function nombre(): string
    {
        return 'Crear puesto';
    }

    public function campos(): array
    {
        return [self::campoUnidad(), Campo::texto('nombre', 'Nombre', maximo: 120)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->organigrama->crearPuesto($datos['unidad_id'], $datos['nombre']);
    }
}
