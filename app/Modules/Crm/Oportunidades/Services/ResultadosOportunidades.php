<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.5 (3, 4 y 5), lienzos `oportunidades-mejoras/Perdidas.dc.html` y `Pronostico.dc.html`: lo que mira todo
 * junto en la vista «Resultados»: por qué perdemos y contra quién, el pronóstico de cierres por mes y las metas de cada
 * ejecutivo. Montos en soles con texto decimal; se calcula al mirar.
 */
final readonly class ResultadosOportunidades
{
    public const array PERIODOS = ['mes' => 'Este mes', 'trimestre' => 'Trimestre', 'anio' => 'Año'];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} desde, hasta y cómo se dice */
    public function rango(string $periodo): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return match ($periodo) {
            'mes' => [$hoy->startOfMonth(), $hoy->endOfMonth(), mb_strtolower($hoy->locale('es')->translatedFormat('F Y'))],
            'anio' => [$hoy->startOfYear(), $hoy->endOfYear(), (string) $hoy->year],
            default => [$hoy->startOfQuarter(), $hoy->endOfQuarter(), $hoy->quarter.'.º trimestre '.$hoy->year],
        };
    }

    /**
     * ¿Por qué perdemos?: las perdidas del periodo por motivo (cantidad, % y monto) y contra quién (el ganador, cuántas
     * veces y cuánto más barato en promedio que nosotros, cuando se sabe su precio).
     *
     * @return array{total: int, monto: string, motivos: list<array{nombre: string, cantidad: int, porcentaje: int, monto: string}>, contra: list<array{nombre: string, veces: int, masBarato: ?string}>}
     */
    public function porQuePerdemos(string $periodo): array
    {
        [$desde, $hasta] = $this->rango($periodo);
        $perdidas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'perdida')
            ->whereBetween('o.fecha_cierre_real', [$desde->toDateString(), $hasta->toDateString()])->get(['o.id', 'o.monto_estimado']);
        $ids = $perdidas->pluck('id')->all();
        $motivoDe = DB::table('historial_estados as h')->join('transiciones_estado as t', 't.id', '=', 'h.transicion_id')->join('motivos as m', 'm.id', '=', 'h.motivo_id')
            ->where('h.documentable_type', 'oportunidad')->whereIn('h.documentable_id', $ids ?: [0])->where('t.codigo', 'perder')
            ->orderBy('h.id')->pluck('m.nombre', 'h.documentable_id');
        $motivos = [];

        foreach ($perdidas as $p) {
            $m = (string) ($motivoDe[$p->id] ?? 'Sin motivo');
            $motivos[$m] ??= ['nombre' => $m, 'cantidad' => 0, 'porcentaje' => 0, 'monto' => '0.00'];
            $motivos[$m]['cantidad']++;
            $motivos[$m]['monto'] = bcadd($motivos[$m]['monto'], (string) $p->monto_estimado, 2);
        }

        $total = count($perdidas);
        $motivos = array_values(array_map(fn (array $m): array => [...$m, 'porcentaje' => $total === 0 ? 0 : (int) round($m['cantidad'] * 100 / $total)], $motivos));
        usort($motivos, fn (array $a, array $b): int => $b['cantidad'] <=> $a['cantidad']);

        $ganadores = DB::table('oportunidad_competidores as c')->join('oportunidades as o', 'o.id', '=', 'c.oportunidad_id')->join('parties as p', 'p.id', '=', 'c.party_id')
            ->whereIn('c.oportunidad_id', $ids ?: [0])->where('c.es_ganador', true)->get(['p.razon_social', 'c.precio', 'o.monto_estimado']);
        $contra = [];

        foreach ($ganadores as $g) {
            $contra[$g->razon_social] ??= ['nombre' => $g->razon_social, 'veces' => 0, 'difs' => []];
            $contra[$g->razon_social]['veces']++;

            if ($g->precio !== null && (float) $g->monto_estimado > 0) {
                $contra[$g->razon_social]['difs'][] = ((float) $g->precio - (float) $g->monto_estimado) * 100 / (float) $g->monto_estimado;
            }
        }

        $contra = array_values(array_map(fn (array $c): array => ['nombre' => $c['nombre'], 'veces' => $c['veces'],
            'masBarato' => $c['difs'] === [] ? null : number_format(array_sum($c['difs']) / count($c['difs']), 1, '.', '')], $contra));
        usort($contra, fn (array $a, array $b): int => $b['veces'] <=> $a['veces']);

        return ['total' => $total, 'monto' => bcadd((string) $perdidas->sum('monto_estimado'), '0', 2), 'motivos' => $motivos, 'contra' => $contra];
    }

    /**
     * Pronóstico de cierres: de las abiertas, por mes de cierre esperado (los próximos 6 desde este), lo ponderado (monto
     * × probabilidad de su etapa) y lo que entraría si se ganara todo. Las de cierre ya vencido cuentan en este mes.
     *
     * @return list<array{mes: string, ponderado: string, total: string, cantidad: int}>
     */
    public function pronostico(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy())->startOfMonth();
        $meses = [];

        for ($i = 0; $i < 6; $i++) {
            $m = $hoy->addMonths($i);
            $meses[$m->format('Y-m')] = ['mes' => ucfirst(str_replace('.', '', $m->locale('es')->translatedFormat('M'))).($m->month === 1 || $i === 0 ? ' '.$m->format('y') : ''), 'ponderado' => '0.00', 'total' => '0.00', 'cantidad' => 0];
        }

        $portada = app(PortadaOportunidades::class);
        $abiertas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->whereIn('e.codigo', array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases()))->get(['o.monto_estimado', 'o.fecha_cierre_estimada', 'e.codigo']);

        foreach ($abiertas as $o) {
            $clave = max($hoy->format('Y-m'), CarbonImmutable::parse($o->fecha_cierre_estimada)->format('Y-m'));

            if (! isset($meses[$clave])) {
                continue;
            }

            $prob = (string) $portada->probabilidad(EtapaAbierta::from($o->codigo));
            $meses[$clave]['ponderado'] = bcadd($meses[$clave]['ponderado'], bcdiv(bcmul((string) $o->monto_estimado, $prob, 4), '100', 4), 2);
            $meses[$clave]['total'] = bcadd($meses[$clave]['total'], (string) $o->monto_estimado, 2);
            $meses[$clave]['cantidad']++;
        }

        return array_values($meses);
    }

    /**
     * Metas por ejecutivo del periodo (la del trimestre; el año suma sus trimestres y el mes es un tercio del trimestre)
     * contra lo ganado (monto de las ganadas con cierre en el periodo): verde cumplida, ámbar 50 % o más, coral menos.
     *
     * @return list<array{id: int, nombre: string, iniciales: string, meta: string, ganado: string, porcentaje: int, falta: string, tono: string}>
     */
    public function metas(string $periodo): array
    {
        [$desde, $hasta] = $this->rango($periodo);
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $metas = DB::table('metas_comerciales')->where('anio', $hoy->year)
            ->when($periodo !== 'anio', fn ($q) => $q->where('trimestre', $hoy->quarter))
            ->groupBy('user_id')->selectRaw('user_id, sum(monto) as monto')->pluck('monto', 'user_id');
        $ganado = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'ganada')
            ->whereBetween('o.fecha_cierre_real', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('o.ejecutivo_user_id')->selectRaw('o.ejecutivo_user_id as quien, sum(o.monto_estimado) as total')->pluck('total', 'quien');
        $filas = [];

        foreach (DB::table('users')->whereIn('id', $metas->keys())->orderBy('name')->get(['id', 'name']) as $u) {
            $meta = (string) $metas[$u->id];
            $meta = $periodo === 'mes' ? bcdiv($meta, '3', 2) : bcadd($meta, '0', 2);
            $g = bcadd((string) ($ganado[$u->id] ?? '0'), '0', 2);
            $pct = bccomp($meta, '0', 2) > 0 ? (int) floor((float) $g * 100 / (float) $meta) : 0;
            $partes = preg_split('/\s+/', trim($u->name)) ?: [];
            $filas[] = ['id' => (int) $u->id, 'nombre' => $u->name, 'iniciales' => mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1)),
                'meta' => $meta, 'ganado' => $g, 'porcentaje' => $pct, 'falta' => bccomp($g, $meta, 2) >= 0 ? '0.00' : bcsub($meta, $g, 2),
                'tono' => $pct >= 100 ? 'verde' : ($pct >= 50 ? 'ambar' : 'coral')];
        }

        return $filas;
    }

    /** @return list<array{user_id: int, anio: int, trimestre: int, monto: string}> */
    public function metasGuardadas(int $anio): array
    {
        return DB::table('metas_comerciales')->where('anio', $anio)->orderBy('trimestre')->orderBy('user_id')->get(['user_id', 'anio', 'trimestre', 'monto'])
            ->map(fn (object $m): array => ['user_id' => (int) $m->user_id, 'anio' => (int) $m->anio, 'trimestre' => (int) $m->trimestre, 'monto' => (string) $m->monto])->all();
    }

    /** @param  list<array{user_id: int|string, anio: int|string, trimestre: int|string, monto: string|int|float}>  $filas  las metas de ese año (reemplaza) */
    public function guardarMetas(int $anio, array $filas, int $autor): void
    {
        DB::transaction(function () use ($anio, $filas, $autor): void {
            DB::table('metas_comerciales')->where('anio', $anio)->delete();

            foreach ($filas as $f) {
                DB::table('metas_comerciales')->updateOrInsert(['user_id' => (int) $f['user_id'], 'anio' => $anio, 'trimestre' => (int) $f['trimestre']],
                    ['monto' => bcadd((string) $f['monto'], '0', 2), 'created_by' => $autor, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
