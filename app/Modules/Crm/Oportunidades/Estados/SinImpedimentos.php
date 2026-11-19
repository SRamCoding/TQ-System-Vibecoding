<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Estados;

use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentosDeOportunidad;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Perder o retroceder con trabajo vivo de otro módulo (plan 2.21, D12.3): el mensaje dice qué lo impide. */
final readonly class SinImpedimentos implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $impiden = app(ImpedimentosDeOportunidad::class)->para((int) $documento->getKey(), $transicion->codigo);

        return $impiden === [] ? null : 'No se puede: '.implode(', ', $impiden).'. Resuélvelo primero.';
    }
}
