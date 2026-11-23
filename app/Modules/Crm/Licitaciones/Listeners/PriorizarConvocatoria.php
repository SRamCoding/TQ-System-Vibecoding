<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Listeners;

use App\Modules\Crm\Licitaciones\Events\ConvocatoriaCambio;
use App\Modules\Crm\Licitaciones\Events\ConvocatoriaLlego;
use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;

/**
 * Observación 7.4, reacción «Priorizar»: prioritaria si el valor referencial es alto (parámetro, desde S/ 500,000) o
 * si la entidad ya es cuenta de Techquk.
 */
final class PriorizarConvocatoria
{
    public function handle(ConvocatoriaLlego|ConvocatoriaCambio $evento): void
    {
        app(ConvocatoriasSeaceService::class)->priorizar($evento->convocatoriaId);
    }
}
