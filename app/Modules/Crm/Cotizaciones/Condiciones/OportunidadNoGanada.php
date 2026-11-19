<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** D5: la aceptación de una oportunidad ganada no se anula: primero se anula la ganada. */
final readonly class OportunidadNoGanada implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        $oportunidad = app(OportunidadesService::class)->resumen($documento->oportunidad_id);

        return $oportunidad->etapa === 'ganada'
            ? "La oportunidad {$oportunidad->numero} ya está ganada con esta cotización: anula la ganada primero."
            : null;
    }
}
