<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\SaldoPartida;
use App\Modules\Erp\Proyectos\Resumen\FiltroSalud;

/** «Con partidas excedidas» (plan 2.61, D4): alguna partida comprometida por encima de lo presupuestado. Lleva costos. */
final class FiltroPartidasExcedidas implements FiltroSalud
{
    public function clave(): string
    {
        return 'partidas-excedidas';
    }

    public function etiqueta(): string
    {
        return 'Con partidas excedidas';
    }

    public function tono(): string
    {
        return 'warning';
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
        return SaludDesdeElArbol::donde(fn (array $d): bool => array_filter(
            $d['partidas'],
            fn (array $p): bool => ! $p['es_no_presupuestado'] && $p['saldo'] instanceof SaldoPartida && $p['saldo']->excedida(),
        ) !== []);
    }
}
