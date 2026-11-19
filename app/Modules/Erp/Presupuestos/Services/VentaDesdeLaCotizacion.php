<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;

/**
 * MVP rápido (fichas t0-datos-demo y t1-puente-erp): mientras no exista Contratos, lo vendido de un proyecto es el
 * total sin IGV de la cotización aceptada de la oportunidad de la que nació. Sin eso, null. Con Contratos, sale del
 * contrato.
 */
final readonly class VentaDesdeLaCotizacion implements VentaDelProyecto
{
    public function venta(int $proyectoId): ?string
    {
        $oportunidad = app(ProyectosService::class)->oportunidadDe($proyectoId);

        return $oportunidad === null ? null : app(CotizacionDeOportunidad::class)->aceptada($oportunidad)?->subtotal;
    }
}
