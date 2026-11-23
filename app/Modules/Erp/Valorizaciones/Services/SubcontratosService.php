<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Services;

use App\Modules\Erp\Valorizaciones\Excepciones\SubcontratoRechazado;
use App\Modules\Erp\Valorizaciones\Models\Subcontrato;
use App\Modules\Erp\Valorizaciones\Models\SubcontratoValorizacion;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * MVP rápido (ficha t4-valorizaciones, observaciones 6, punto 12): el estado de cada subcontrato en un solo cálculo, el
 * que usan las tarjetas, la bandeja, las cifras, la ficha y Mien: el dinero (pagado, aprobado por pagar, por aprobar y
 * por ejecutar), el adelanto por amortizar, la retención por devolver y la alarma «le pagas más avance del que cobras».
 */
final readonly class SubcontratosService
{
    public const string PERMISO_LIBERAR = 'erp.valorizaciones.liberar-retencion';

    /** Puntos de avance del subcontratista por delante de lo valorizado al cliente a partir de los que se avisa. */
    public const string UMBRAL_ALARMA = '10';

    /** Días de espera de una valorización por aprobar a partir de los que avisa Mien. */
    public const int DIAS_AVISO = 3;

    /**
     * El estado de todos los subcontratos (o de uno), en el orden por urgencia.
     *
     * @return list<array<string, mixed>>
     */
    public function estados(?int $soloId = null): array
    {
        $subs = Subcontrato::query()->when($soloId !== null, fn ($q) => $q->whereKey($soloId))->orderBy('codigo')->get();
        $proyectos = DB::table('proyectos')->whereIn('id', $subs->pluck('proyecto_id')->unique())->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $valorizaciones = SubcontratoValorizacion::query()->whereIn('subcontrato_id', $subs->pluck('id'))->orderBy('numero')->get()->groupBy('subcontrato_id');
        $alcance = DB::table('subcontrato_alcance as a')->join('partidas_control as p', 'p.id', '=', 'a.partida_control_id')
            ->leftJoin('imputaciones as i', fn ($j) => $j->on('i.origen_id', '=', 'a.id')->where('i.origen_type', '=', 'subcontrato_alcance'))
            ->whereIn('a.subcontrato_id', $subs->pluck('id'))->orderBy('p.codigo')
            ->get(['a.id', 'a.subcontrato_id', 'a.monto', 'p.codigo', 'p.nombre', 'i.monto_comprometido', 'i.monto_ejecutado'])->groupBy('subcontrato_id');
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $acumulados = [];
        $estados = [];

        foreach ($subs as $sc) {
            $vals = $valorizaciones->get($sc->id, collect());
            $suma = fn (array $estadosVal, string $col): string => bcadd($vals->whereIn('estado', $estadosVal)->reduce(fn (string $s, SubcontratoValorizacion $v): string => bcadd($s, (string) $v->{$col}, 2), '0'), '0', 2);
            $pagado = $suma(['pagada'], 'monto');
            $aprobado = $suma(['aprobada'], 'monto');
            $porAprobar = $suma(['por_aprobar'], 'monto');
            $porEjecutar = bcsub((string) $sc->monto, bcadd(bcadd($pagado, $aprobado, 2), $porAprobar, 2), 2);
            $adelanto = bcadd(bcdiv(bcmul((string) $sc->monto, (string) $sc->adelanto_pct, 6), '100', 6), '0.005', 2);
            $retenido = $suma(['aprobada', 'pagada'], 'retencion');
            $liberada = $sc->retencion_liberada === null ? '0.00' : bcadd((string) $sc->retencion_liberada, '0', 2);
            $liberadaPorPagar = $sc->retencion_pagada ? '0.00' : $liberada;
            $avance = $suma(['por_aprobar', 'aprobada', 'pagada'], 'avance_pct');
            $avanceAprobado = $suma(['aprobada', 'pagada'], 'avance_pct');
            $lineas = $alcance->get($sc->id, collect());
            $cliente = $this->avanceCliente((int) $sc->proyecto_id, $lineas->all(), $acumulados);
            $terminado = $sc->estado === 'terminado';
            $diferencia = $cliente === null ? null : bcsub($avance, $cliente, 2);
            $alarma = ! $terminado && $diferencia !== null && bccomp($diferencia, self::UMBRAL_ALARMA, 2) > 0;
            $pendientes = $vals->where('estado', 'por_aprobar')->values();
            $porPagar = bcadd($suma(['aprobada'], 'neto'), $liberadaPorPagar, 2);
            $p = $proyectos->get($sc->proyecto_id);

            $estados[] = [
                'id' => $sc->id, 'codigo' => $sc->codigo, 'subcontratista' => $sc->subcontratista, 'iniciales' => self::iniciales($sc->subcontratista), 'objeto' => $sc->objeto,
                'proyecto_id' => (int) $sc->proyecto_id, 'proyecto' => (string) ($p->codigo ?? ''), 'proyecto_nombre' => (string) ($p->nombre ?? ''),
                'monto' => bcadd((string) $sc->monto, '0', 2), 'adelanto_pct' => (string) $sc->adelanto_pct, 'retencion_pct' => (string) $sc->retencion_pct,
                'pagado' => $pagado, 'aprobado' => $aprobado, 'por_aprobar' => $porAprobar, 'por_ejecutar' => bccomp($porEjecutar, '0', 2) < 0 ? '0.00' : $porEjecutar,
                'adelanto' => $adelanto, 'por_amortizar' => bcsub($adelanto, $suma(['aprobada', 'pagada'], 'amortizacion'), 2),
                'retencion' => bcsub($retenido, $liberada, 2), 'retencion_liberada' => $liberada, 'retencion_pagada' => (bool) $sc->retencion_pagada,
                'neto_por_aprobar' => $suma(['por_aprobar'], 'neto'), 'por_pagar' => $porPagar,
                'avance' => $avance, 'avance_aprobado' => $avanceAprobado, 'cliente' => $cliente, 'diferencia' => $diferencia, 'alarma' => $alarma,
                'terminado' => $terminado, 'listo_liberar' => ! $terminado && $pendientes->isEmpty() && bccomp($avanceAprobado, '100', 2) === 0,
                'conformidad_fecha' => $sc->conformidad_fecha, 'conformidad_nota' => $sc->conformidad_nota,
                'pendientes' => $pendientes->map(fn (SubcontratoValorizacion $v): array => [...$this->fila($v), 'dias' => (int) CarbonImmutable::parse($v->created_at)->startOfDay()->diffInDays($hoy)])->all(),
                'valorizaciones' => $vals->map(fn (SubcontratoValorizacion $v): array => $this->fila($v))->all(),
                'alcance' => $lineas->map(fn (object $l): array => ['codigo' => $l->codigo, 'nombre' => $l->nombre, 'monto' => bcadd((string) $l->monto, '0', 2),
                    'comprometido' => bcadd((string) ($l->monto_comprometido ?? $l->monto), '0', 2), 'ejecutado' => bcadd((string) ($l->monto_ejecutado ?? '0'), '0', 2)])->all(),
            ];
        }

        // Orden por urgencia: algo por aprobar, la alarma, algo por pagar, el resto y, al final, los terminados.
        foreach ($estados as &$e) {
            $e['urgencia'] = match (true) {
                $e['terminado'] => 4,
                $e['pendientes'] !== [] => 0,
                $e['alarma'] => 1,
                bccomp($e['por_pagar'], '0', 2) > 0 => 2,
                default => 3,
            };
        }

        unset($e);
        usort($estados, fn (array $a, array $b): int => [$a['urgencia'], -max([0, ...array_column($a['pendientes'], 'dias')]), $a['codigo']] <=> [$b['urgencia'], -max([0, ...array_column($b['pendientes'], 'dias')]), $b['codigo']]);

        return $estados;
    }

    /** @return array<string, mixed>|null */
    public function estado(int $id): ?array
    {
        return $this->estados($id)[0] ?? null;
    }

    /**
     * Las cinco cifras de arriba, del mismo cálculo que las tarjetas.
     *
     * @param  list<array<string, mixed>>  $estados
     * @return array{vigentes: int, comprometido: string, por_aprobar_cantidad: int, por_aprobar: string, por_pagar: string, retenciones: string}
     */
    public function cifras(array $estados): array
    {
        $sum = fn (string $k, ?callable $f = null): string => array_reduce(array_filter($estados, $f ?? fn (): bool => true), fn (string $s, array $e): string => bcadd($s, $e[$k], 2), '0.00');
        $vigente = fn (array $e): bool => ! $e['terminado'];

        return [
            'vigentes' => count(array_filter($estados, $vigente)),
            'comprometido' => $sum('monto', $vigente),
            'por_aprobar_cantidad' => array_sum(array_map(fn (array $e): int => count($e['pendientes']), $estados)),
            'por_aprobar' => $sum('neto_por_aprobar'),
            'por_pagar' => $sum('por_pagar'),
            'retenciones' => $sum('retencion'),
        ];
    }

    /**
     * Con el subcontrato al 100 % aprobado y su conformidad, se devuelve la retención acumulada: queda por pagar al
     * subcontratista y el subcontrato pasa a terminado.
     */
    public function liberarRetencion(int $id, CarbonImmutable $conformidad, string $nota): Subcontrato
    {
        return DB::transaction(function () use ($id, $conformidad, $nota): Subcontrato {
            $sc = Subcontrato::query()->lockForUpdate()->find($id) ?? throw SubcontratoRechazado::conMotivo('Ese subcontrato no existe.');

            if ($sc->estado === 'terminado') {
                throw SubcontratoRechazado::conMotivo("{$sc->codigo} ya está terminado: su retención se liberó.");
            }

            if (SubcontratoValorizacion::query()->where('subcontrato_id', $sc->id)->where('estado', 'por_aprobar')->exists()) {
                throw SubcontratoRechazado::conMotivo('Primero aprueba la valorización pendiente.');
            }

            $avance = (string) (SubcontratoValorizacion::query()->where('subcontrato_id', $sc->id)->whereIn('estado', ['aprobada', 'pagada'])->sum('avance_pct') ?: '0');

            if (bccomp($avance, '100', 2) !== 0) {
                throw SubcontratoRechazado::conMotivo("{$sc->codigo} va al ".rtrim(rtrim($avance, '0'), '.').' %: la retención se libera con el 100 % aprobado.');
            }

            if ($conformidad->toDateString() > Calendario::hoy()) {
                throw SubcontratoRechazado::conMotivo('La conformidad no puede tener fecha futura.');
            }

            $sc->update(['estado' => 'terminado', 'conformidad_fecha' => $conformidad->toDateString(), 'conformidad_nota' => mb_substr(trim($nota), 0, 500),
                'retencion_liberada' => app(ValorizacionesService::class)->retencionAcumulada($sc->id), 'retencion_pagada' => false]);

            return $sc;
        });
    }

    /**
     * El avance que Techquk le valorizó al cliente en las mismas partidas del alcance: el acumulado aprobado de cada partida
     * del presupuesto (PC-03.01 es la 03.01), ponderado por el monto de cada partida en el alcance. Null si ninguna tiene
     * valorización al cliente.
     *
     * @param  list<object>  $lineas
     * @param  array<int, array<string, string>>  $cache
     */
    private function avanceCliente(int $proyectoId, array $lineas, array &$cache): ?string
    {
        $contrato = DB::table('contratos')->where('proyecto_id', $proyectoId)->orderBy('id')->value('id');

        if ($contrato === null || $lineas === []) {
            return null;
        }

        $acumulado = $cache[$contrato] ??= app(ValorizacionesService::class)->acumulado((int) $contrato);
        $total = '0';
        $ponderado = '0';
        $alguna = false;

        foreach ($lineas as $l) {
            $pct = $acumulado[preg_replace('/^PC-/', '', (string) $l->codigo)] ?? '0';
            $alguna = $alguna || bccomp($pct, '0', 2) > 0;
            $total = bcadd($total, (string) $l->monto, 2);
            $ponderado = bcadd($ponderado, bcmul((string) $l->monto, $pct, 6), 6);
        }

        return ! $alguna || bccomp($total, '0', 2) === 0 ? null : bcadd(bcdiv($ponderado, $total, 6), '0.005', 2);
    }

    /** @return array<string, mixed> */
    private function fila(SubcontratoValorizacion $v): array
    {
        return ['id' => $v->id, 'numero' => (int) $v->numero, 'periodo' => $v->periodo, 'avance_pct' => (string) $v->avance_pct, 'monto' => (string) $v->monto,
            'amortizacion' => (string) $v->amortizacion, 'retencion' => (string) $v->retencion, 'neto' => (string) $v->neto, 'estado' => $v->estado,
            'fecha' => CarbonImmutable::parse($v->created_at)->toDateString()];
    }

    private static function iniciales(string $nombre): string
    {
        $palabras = array_filter(preg_split('/\s+/u', trim($nombre)) ?: [], fn (string $p): bool => mb_strlen($p) > 2 && ! str_contains($p, '.'));

        return mb_strtoupper(implode('', array_map(fn (string $p): string => mb_substr($p, 0, 1), array_slice(array_values($palabras), 0, 2))));
    }
}
