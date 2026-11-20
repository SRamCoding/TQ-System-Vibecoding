<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Condiciones;

use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t9-cierre, F6): no se cierra un proyecto con algo abierto; el mensaje dice todo lo que falta. */
final readonly class ProyectoSinPendientes implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $b = app(VerificacionesCierre::class)->bloqueos((int) $documento->getKey());

        return $b === [] ? null : 'No se puede cerrar: '.implode('; ', $b).'.';
    }
}
