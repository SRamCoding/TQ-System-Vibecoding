<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament;

use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t3-compras): lo común de las pantallas de compras. */
final class Compras
{
    public static function puede(string $permiso): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can($permiso);
    }

    public static function soles(mixed $m): string
    {
        return $m === null ? '—' : 'S/ '.number_format((float) $m, 2);
    }

    /** «PRY-2026-0012 › PC-02.01 Cable» o «Gasto directo › Administración». */
    public static function imputacion(?int $partidaId, ?int $centroId = null): string
    {
        if ($partidaId !== null) {
            $p = DB::table('partidas_control as p')->join('proyectos as y', 'y.id', '=', 'p.proyecto_id')->where('p.id', $partidaId)->first(['y.codigo as pry', 'p.codigo', 'p.nombre']);

            return "{$p->pry} › {$p->codigo} {$p->nombre}";
        }

        return $centroId === null ? 'sin imputación' : 'Gasto directo › '.DB::table('centros_costo')->where('id', $centroId)->value('nombre');
    }

    /** @return array<int, string> las partidas hoja activas del proyecto (con la PC-99), para imputar */
    public static function partidasDe(?int $proyectoId): array
    {
        return $proyectoId === null ? [] : DB::table('partidas_control as p')
            ->where('p.proyecto_id', $proyectoId)->where('p.es_hoja', true)->where('p.es_activa', true)->orderBy('p.codigo')
            ->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all();
    }
}
