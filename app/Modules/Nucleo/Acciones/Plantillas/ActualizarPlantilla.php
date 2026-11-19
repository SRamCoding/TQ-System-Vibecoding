<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use App\Modules\Nucleo\Services\PlantillasService;

/** Toma el contenido de un documento como versión siguiente de la plantilla (ADR 0022, D3). */
final class ActualizarPlantilla extends AccionPlantillas
{
    public function clave(): string
    {
        return 'nucleo.plantillas.actualizar';
    }

    public function nombre(): string
    {
        return 'Actualizar plantilla desde este documento';
    }

    public function campos(): array
    {
        return [self::campoPlantilla(), self::campoDocumento(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): ResumenPlantilla
    {
        return app(PlantillasService::class)->actualizarDesde($datos['plantilla_id'], $datos['documento_id'], $datos['version'], $usuario);
    }
}
