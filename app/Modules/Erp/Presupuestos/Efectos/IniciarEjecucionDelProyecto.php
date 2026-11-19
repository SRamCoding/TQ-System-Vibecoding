<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/**
 * Al aprobar el árbol, el proyecto pasa a «en ejecución» (v4, flujo 1), por C2. Si ya lo estaba (una versión nueva
 * del árbol), C2 no hace nada. Un árbol aprobado no se anula: no hay nada que revertir.
 */
final readonly class IniciarEjecucionDelProyecto implements Efecto
{
    public function __construct(private ProyectosService $proyectos) {}

    public function aplicar(Model $documento): array
    {
        $proyectoId = (int) $documento->getAttribute('proyecto_id');
        $this->proyectos->iniciarEjecucion($proyectoId);

        return ['proyecto_id' => $proyectoId];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // No se deshace: un árbol aprobado no se anula. Si llega «Reabrir» o «Nueva versión», decidir ahí qué pasa con el estado del proyecto (plan 2.1, bitácora).
    }
}
