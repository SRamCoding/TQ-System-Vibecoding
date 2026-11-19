<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PlantillasService;

/** Una plantilla deja de ofrecerse; no se borra (ADR 0022, regla 7). */
final class DesactivarPlantilla extends AccionPlantillas
{
    public function clave(): string
    {
        return 'nucleo.plantillas.desactivar';
    }

    public function nombre(): string
    {
        return 'Desactivar plantilla';
    }

    public function campos(): array
    {
        return [self::campoPlantilla(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): null
    {
        app(PlantillasService::class)->desactivar($datos['plantilla_id'], $datos['version'], $usuario);

        return null;
    }
}
