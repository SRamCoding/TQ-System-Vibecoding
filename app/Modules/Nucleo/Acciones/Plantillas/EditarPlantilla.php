<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use App\Modules\Nucleo\Services\PlantillasService;

/** Cambia el nombre o la descripción de una plantilla; su contenido no cambia. */
final class EditarPlantilla extends AccionPlantillas
{
    public function clave(): string
    {
        return 'nucleo.plantillas.editar';
    }

    public function nombre(): string
    {
        return 'Editar plantilla';
    }

    public function campos(): array
    {
        return [self::campoPlantilla(), self::campoNombre(), self::campoDescripcion(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): ResumenPlantilla
    {
        return app(PlantillasService::class)->editar($datos['plantilla_id'], $datos['nombre'], $datos['descripcion'] ?? null, $datos['version'], $usuario);
    }
}
