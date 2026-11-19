<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

/**
 * Cuánto se le vende al cliente en un proyecto (plan 2.61, D10): el monto del contrato con sus adendas. Hoy nadie lo
 * sabe y la implementación es `SinVentaDelProyecto` (siempre nulo): la sección de dinero muestra solo costos. Contratos
 * (C10) la reemplaza en su provider y aparecen el margen previsto, el proyectado y su semáforo, sin tocar la ficha.
 */
interface VentaDelProyecto
{
    /** El monto vendido en soles con dos decimales, o null si no se sabe. */
    public function venta(int $proyectoId): ?string;
}
