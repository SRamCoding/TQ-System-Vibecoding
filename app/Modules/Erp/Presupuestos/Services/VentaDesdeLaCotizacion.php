<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;

/**
 * MVP rápido (ficha t0-datos-demo): mientras no exista Contratos, lo vendido de un proyecto es el subtotal sin IGV
 * (costo directo + gastos generales + utilidad) de su presupuesto vigente, si ese presupuesto es el de una oportunidad
 * con cotización aceptada. Sin eso, null (como antes). Con Contratos, la venta sale del contrato.
 */
final readonly class VentaDesdeLaCotizacion implements VentaDelProyecto
{
    public function venta(int $proyectoId): ?string
    {
        $vigente = Presupuesto::query()->where('proyecto_id', $proyectoId)->where('es_vigente', true)->first();

        if ($vigente === null || $vigente->oportunidad_id === null || app(CotizacionDeOportunidad::class)->aceptada($vigente->oportunidad_id) === null) {
            return null;
        }

        $r = app(PresupuestosService::class)->resumen($vigente->id);

        return bcadd(bcadd($r->costoDirecto, $r->gastosGenerales, 2), $r->utilidad, 2);
    }
}
