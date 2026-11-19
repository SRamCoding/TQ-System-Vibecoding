<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use Closure;
use LogicException;

/**
 * El proyecto que pidió «Traer al proyecto» (plan 2.9, D6), para su efecto. La transición es de acción propia: la
 * acción recibe el proyecto y el servicio lo deja aquí mientras corre la transición; el efecto lo lee. Fuera de eso
 * no hay pedido (un efecto sin pedido falla: nadie aprueba un presupuesto sin decir a qué proyecto va).
 */
final class PedidoTraerAlProyecto
{
    private ?int $proyectoId = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    public function durante(int $proyectoId, Closure $tarea): mixed
    {
        $this->proyectoId = $proyectoId;

        try {
            return $tarea();
        } finally {
            $this->proyectoId = null;
        }
    }

    public function proyectoId(): int
    {
        return $this->proyectoId ?? throw new LogicException('«Traer al proyecto» se pide con PresupuestosService::traerAlProyecto(), que dice a qué proyecto.');
    }
}
