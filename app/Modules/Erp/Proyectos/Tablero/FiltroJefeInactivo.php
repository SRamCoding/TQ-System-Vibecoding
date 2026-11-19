<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Tablero;

use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\FiltroSalud;
use App\Modules\Erp\Proyectos\Services\PortadaProyectos;

/** «Jefe inactivo» (el aviso de la 2.10, ahora opción de «Salud»): abiertos cuyo jefe ya no está activo. */
final class FiltroJefeInactivo implements FiltroSalud
{
    public function clave(): string
    {
        return 'jefe-inactivo';
    }

    public function etiqueta(): string
    {
        return 'Jefe inactivo';
    }

    public function tono(): string
    {
        return 'warning';
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
        return app(PortadaProyectos::class)->enAviso(Proyecto::query(), 'jefe-inactivo')
            ->pluck('proyectos.id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
