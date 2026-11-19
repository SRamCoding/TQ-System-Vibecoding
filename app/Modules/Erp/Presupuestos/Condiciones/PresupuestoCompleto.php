<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Condiciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** No se oferta ni se trae al proyecto un presupuesto incompleto (plan 2.9, D9). */
final readonly class PresupuestoCompleto implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        if (! $documento instanceof Presupuesto) {
            return 'Esta condición es del presupuesto.';
        }

        $faltas = app(ConsultaPresupuestos::class)->faltas($documento);

        return $faltas === [] ? null : 'El presupuesto está incompleto: '.implode('; ', $faltas).'.';
    }
}
