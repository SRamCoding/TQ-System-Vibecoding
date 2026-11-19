<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PlantillasService;

/** Publica una plantilla personal en la empresa, con el permiso de su tipo (ADR 0022, D2). */
final class PublicarPlantilla extends AccionPlantillas
{
    public function clave(): string
    {
        return 'nucleo.plantillas.publicar';
    }

    public function nombre(): string
    {
        return 'Publicar plantilla en la empresa';
    }

    public function campos(): array
    {
        return [self::campoPlantilla(), Campo::texto('nombre', 'Nombre en la empresa', maximo: 160)->opcional()];
    }

    public function ejecutar(array $datos, User $usuario): ResumenPlantilla
    {
        return app(PlantillasService::class)->publicar($datos['plantilla_id'], $datos['nombre'] ?? null, $usuario);
    }
}
