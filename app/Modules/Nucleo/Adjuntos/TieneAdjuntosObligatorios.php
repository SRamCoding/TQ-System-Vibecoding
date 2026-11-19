<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Adjuntos;

use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AdjuntosService;
use Illuminate\Database\Eloquent\Model;

/**
 * Condición de transición (plan 1.8, D4): el documento tiene un adjunto vigente de cada tipo obligatorio. El módulo
 * la pone en la transición donde corresponde (por ejemplo, «Enviar» exige la cotización).
 */
final readonly class TieneAdjuntosObligatorios implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $faltan = app(AdjuntosService::class)->obligatoriosQueFaltan($documento);

        return $faltan === [] ? null : 'Falta adjuntar: '.implode(', ', $faltan).'.';
    }
}
