<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Condiciones;

use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ImputacionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Un proyecto con costos no se anula (plan 2.1, D3): sus líneas quedarían apuntando a un proyecto anulado. Qué pasa con
 * uno que nació de un contrato lo decide la tarea de Contratos.
 */
final readonly class ProyectoSinImputaciones implements CondicionTransicion
{
    public function __construct(private ImputacionService $imputaciones) {}

    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        return $this->imputaciones->tieneLineasDelProyecto((int) $documento->getKey())
            ? 'El proyecto ya tiene costos imputados: no se puede anular.'
            : null;
    }
}
