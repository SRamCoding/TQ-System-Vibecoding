<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Services;

use App\Modules\Erp\Presupuestos\Services\VentaDesdeLaCotizacion;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;

/**
 * MVP rápido (t2-contratos): lo vendido sale del contrato (monto más adendas, sin IGV, menos penalidades); un proyecto
 * sin contrato sigue con la cotización aceptada.
 */
final readonly class VentaDesdeElContrato implements VentaDelProyecto
{
    public function venta(int $proyectoId): ?string
    {
        return app(ContratosService::class)->ventaNeta($proyectoId) ?? (new VentaDesdeLaCotizacion)->venta($proyectoId);
    }
}
