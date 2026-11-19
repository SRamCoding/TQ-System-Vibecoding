<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Estados;

use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** No se avanza sin cumplir lo que la etapa exige, acumulado hasta la destino (plan 2.21, D9; v4: «muestra el faltante»). */
final readonly class CumpleRequisitosDeEtapa implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $destino = (string) Estado::query()->whereKey($transicion->hacia_estado_id)->value('codigo');
        $faltan = app(RequisitosDeEtapa::class)->faltantes((int) $documento->getKey(), $destino);

        return $faltan === [] ? null : 'Para avanzar falta: '.implode(', ', $faltan).'.';
    }
}
