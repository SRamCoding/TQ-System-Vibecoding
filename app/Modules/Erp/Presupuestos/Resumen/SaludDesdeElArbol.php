<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Proyectos\Resumen\SemaforoDeCosto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Models\User;

/**
 * El semáforo de la tarjeta de la portada y los filtros de salud por costo (plan 2.61, D4, D10 y D11), desde
 * `DineroDelProyecto`. Solo los proyectos con árbol tienen dato. Lleva costos: Proyectos solo lo pide para quien los ve.
 */
final class SaludDesdeElArbol implements SemaforoDeCosto
{
    public function tonos(array $proyectos, User $usuario): array
    {
        if (! $usuario->can(PresupuestosServiceProvider::PERMISO_VER)) {
            return [];
        }

        $tonos = [];

        foreach (self::conArbol($proyectos) as $id) {
            $dinero = app(DineroDelProyecto::class)->de($id);

            if ($dinero !== null) {
                $tonos[$id] = $dinero['tono'];
            }
        }

        return $tonos;
    }

    /**
     * Los proyectos con árbol (de esa lista, o todos) que cumplen la condición sobre su dinero.
     *
     * @param  callable(array<string, mixed>): bool  $cumple
     * @return list<int>
     */
    public static function donde(callable $cumple): array
    {
        $ids = [];

        foreach (self::conArbol(null) as $id) {
            $dinero = app(DineroDelProyecto::class)->de($id);

            if ($dinero !== null && $cumple($dinero)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Los proyectos con árbol aprobado (D14) de esa lista; sin lista, los abiertos (revisión de arquitectura: un filtro de
     * salud no recalcula los cerrados y anulados, que se acumulan).
     *
     * @param  ?list<int>  $proyectos
     * @return list<int>
     */
    private static function conArbol(?array $proyectos): array
    {
        $consulta = ArbolPartidas::query()->distinct()
            ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionArbolPartidas::APROBADO))
            ->whereIn('proyecto_id', $proyectos ?? app(ProyectosService::class)->idsAbiertos());

        return $consulta->pluck('proyecto_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
