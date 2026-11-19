<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Tablero;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\FiltroSalud;
use App\Modules\Nucleo\Tiempo\Calendario;

/**
 * «Plazo vencido» (plan 2.61, D9): abiertos con el fin previsto ya pasado. «Atrasados», por avance físico contra plazo,
 * lo suma el módulo de fases.
 */
final class FiltroVencidos implements FiltroSalud
{
    public function clave(): string
    {
        return 'vencidos';
    }

    public function etiqueta(): string
    {
        return 'Plazo vencido';
    }

    public function tono(): string
    {
        return 'danger';
    }

    public function exigeCostos(): bool
    {
        return false;
    }

    public function permiso(): ?string
    {
        return null;
    }

    public function proyectos(): array
    {
        return Proyecto::query()
            ->whereHas('estado', fn ($query) => $query->whereIn('codigo', DefinicionProyecto::ACEPTAN_COSTOS))
            ->whereDate('fecha_fin_prevista', '<', Calendario::ahora()->toDateString())
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
