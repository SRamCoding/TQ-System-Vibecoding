<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Listeners;

use App\Modules\Crm\Licitaciones\Events\ConvocatoriaCambio;
use App\Modules\Crm\Licitaciones\Events\ConvocatoriaLlego;
use App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias;

/** Observación 7.4, reacción «Filtrar»: decide si entra a la bandeja (palabras clave y valor mínimo). */
final class FiltrarConvocatoria
{
    public function handle(ConvocatoriaLlego|ConvocatoriaCambio $evento): void
    {
        app(FiltroConvocatorias::class)->aplicar($evento->convocatoriaId);
    }
}
