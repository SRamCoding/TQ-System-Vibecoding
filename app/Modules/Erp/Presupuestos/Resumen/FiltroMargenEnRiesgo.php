<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Proyectos\Resumen\FiltroSalud;

/** «Costo en riesgo» (plan 2.61, D11): la proyección al cierre en rojo. Con el contrato (D10), el margen. Lleva costos. */
final class FiltroMargenEnRiesgo implements FiltroSalud
{
    public function clave(): string
    {
        return 'costo-en-riesgo';
    }

    public function etiqueta(): string
    {
        return 'Costo en riesgo';
    }

    public function tono(): string
    {
        return 'danger';
    }

    public function exigeCostos(): bool
    {
        return true;
    }

    public function permiso(): string
    {
        return PresupuestosServiceProvider::PERMISO_VER;
    }

    public function proyectos(): array
    {
        return SaludDesdeElArbol::donde(fn (array $d): bool => $d['tono'] === 'danger');
    }
}
