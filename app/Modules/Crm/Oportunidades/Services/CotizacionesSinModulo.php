<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/** La versión de fábrica de CotizacionDeOportunidad mientras Cotizaciones no esté: «no hay aceptada» (ADR 0020, regla 3). */
final class CotizacionesSinModulo implements CotizacionDeOportunidad
{
    public function aceptada(int $oportunidadId): ?CotizacionAceptada
    {
        return null;
    }

    public function versiones(int $oportunidadId): array
    {
        return [];
    }

    public function margenes(array $oportunidadIds): array
    {
        return [];
    }
}
