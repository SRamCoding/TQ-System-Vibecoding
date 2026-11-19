<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * D21: una cotización la mueve el ejecutivo de su oportunidad, o quien puede sobre todas (Gerencia Comercial). El núcleo
 * revisa el permiso de la transición; esto revisa de quién es.
 */
final readonly class SobreLaOportunidadPropia implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        return app(CotizacionesService::class)->puedeSobre($usuario, $documento->oportunidad_id)
            ? null
            : 'Solo el ejecutivo de la oportunidad, o quien puede sobre todas las cotizaciones, puede hacerlo.';
    }
}
