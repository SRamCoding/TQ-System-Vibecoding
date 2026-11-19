<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Services;

use App\Modules\Crm\Oportunidades\Services\ProximaAccionDeOportunidades;

/** MVP rápido (t1-agenda): la próxima acción de las oportunidades sale de sus actividades pendientes. */
final class ProximaAccionDesdeActividades implements ProximaAccionDeOportunidades
{
    public function existe(): bool
    {
        return true;
    }

    public function deLote(array $oportunidadIds): array
    {
        return app(ActividadesService::class)->proximasDeOportunidades($oportunidadIds);
    }
}
