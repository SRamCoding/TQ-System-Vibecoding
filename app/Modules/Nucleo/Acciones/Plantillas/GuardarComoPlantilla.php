<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PlantillasService;

/** «Guardar como plantilla»: un documento bien hecho queda como plantilla personal (ADR 0022, regla 3). */
final class GuardarComoPlantilla extends AccionPlantillas
{
    public function clave(): string
    {
        return 'nucleo.plantillas.guardar';
    }

    public function nombre(): string
    {
        return 'Guardar como plantilla';
    }

    public function campos(): array
    {
        return [
            Campo::texto('tipo', 'Tipo de plantilla', maximo: 80),
            self::campoDocumento(),
            self::campoNombre(),
            self::campoDescripcion(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): ResumenPlantilla
    {
        return app(PlantillasService::class)->guardar($datos['tipo'], $datos['documento_id'], $datos['nombre'], $datos['descripcion'] ?? null, $usuario);
    }
}
