<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** D4: se cotiza, emite y acepta solo sobre una oportunidad abierta; las de una cerrada quedan como historia. */
final readonly class OportunidadAbierta implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        $oportunidad = app(OportunidadesService::class)->resumen($documento->oportunidad_id);

        return $oportunidad->abierta ? null : "La oportunidad {$oportunidad->numero} ya está cerrada.";
    }
}
