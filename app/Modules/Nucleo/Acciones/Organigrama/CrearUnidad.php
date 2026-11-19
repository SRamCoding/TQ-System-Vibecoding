<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Crea una unidad del organigrama, en la raíz o dentro de otra activa. Devuelve su id. */
final class CrearUnidad extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'crear-unidad';
    }

    public function nombre(): string
    {
        return 'Crear unidad';
    }

    public function campos(): array
    {
        return self::camposDeUnidad();
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->organigrama->crearUnidad(self::datosUnidad($datos));
    }
}
