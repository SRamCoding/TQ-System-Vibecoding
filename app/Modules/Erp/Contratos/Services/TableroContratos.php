<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Services;

use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Models\HitoContrato;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Observación 6.11 (ficha t2-contratos): lo que muestran las tarjetas de `/contratos`, sus cinco cifras, sus filtros, el
 * calendario de vencimientos y los avisos de Mien. Todo sale de `ContratosService` (monto y plazo vigentes, penalidad
 * diaria, tope, avance valorizado): la tarjeta, la ficha y el tablero del proyecto dicen los mismos números.
 */
final readonly class TableroContratos
{
    /** Más de estos puntos de avance detrás del tiempo: «atrasado». */
    public const int PUNTOS_ATRASO = 10;

    public const array FILTROS = ['vigentes' => 'Vigentes', 'por_completar' => 'Por completar', 'por_vencer' => 'Por vencer (30 días)', 'vencidos' => 'Vencidos',
        'con_penalidad' => 'Con penalidad', 'culminados' => 'Culminados'];

    public const array ORDENES = ['urgencia' => 'Urgencia', 'monto' => 'Monto', 'dias' => 'Días restantes', 'atraso' => 'Atraso', 'cliente' => 'Cliente'];

    private const array MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function __construct(private ContratosService $contratos) {}

    /**
     * Una tarjeta por contrato (sin los anulados).
     *
     * @return list<array<string, mixed>>
     */
    public function tarjetas(): array
    {
        return Contrato::query()->with(['cliente', 'hitos'])->where('estado', '!=', 'anulado')->orderBy('codigo')->get()
            ->map(fn (Contrato $c): array => $this->tarjeta($c))->all();
    }

    /** @return array<string, mixed> */
    public function tarjeta(Contrato $c): array
    {
        $s = $this->contratos;
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $vigente = $c->estado === 'vigente';
        $cerrado = in_array($c->estado, ['culminado', 'liquidado', 'resuelto'], true);
        $restan = $s->diasRestantes($c);
        $tiempo = $cerrado ? '100.0' : $s->tiempoPct($c);
        $avance = $s->avancePct($c);
        $atraso = $avance === null ? null : self::entero(bcsub($tiempo, $avance, 1));
        $aplicada = $s->penalidadAplicada($c);
        $riesgo = $s->penalidadEnRiesgo($c);
        $fianza = $this->fianza($c, $hoy);

        return [
            'id' => $c->id, 'codigo' => $c->codigo, 'numero_entidad' => $c->numero_entidad, 'objeto' => $c->objeto,
            'cliente' => (string) $c->cliente?->razon_social, 'cliente_id' => $c->cliente_party_id,
            'estado' => $c->estado, 'estado_etiqueta' => Contrato::ESTADOS[$c->estado], 'estado_color' => Contrato::COLORES[$c->estado],
            'monto' => $s->montoVigente($c), 'tiene_adendas' => $c->adendas()->exists(),
            'plazo' => $s->plazoVigente($c), 'fin' => $s->fin($c), 'restan' => $restan, 'vencido' => $vigente && $restan < 0,
            'tiempo_pct' => $tiempo, 'plazo_texto' => $this->textoPlazo($c->estado, $restan),
            'avance_pct' => $avance, 'atraso' => $atraso, 'atrasado' => $vigente && $atraso !== null && $atraso > self::PUNTOS_ATRASO,
            'hitos' => $this->hitos($c, $hoy), 'diaria' => $s->penalidadDiaria($c), 'tope' => $s->tope($c), 'aplicada' => $aplicada, 'riesgo' => $riesgo,
            'con_penalidad' => bccomp($aplicada, '0', 2) > 0 || bccomp($riesgo, '0', 2) > 0,
            'fianza' => $fianza, 'fianza_30' => ! $cerrado && $fianza['dias'] !== null && $fianza['dias'] <= 30,
        ];
    }

    /**
     * Las cinco cifras de arriba: vigentes, monto vigente, penalidad en riesgo, cartas fianza a 30 días y lo garantizado.
     *
     * @param  list<array<string, mixed>>  $tarjetas
     * @return array<string, string>
     */
    public function cifras(array $tarjetas): array
    {
        $vigentes = array_filter($tarjetas, fn (array $t): bool => $t['estado'] === 'vigente');
        $suma = fn (array $lista, string $campo): string => array_reduce($lista, fn (string $total, array $t): string => bcadd($total, (string) $t[$campo], 2), '0.00');
        $hasta = CarbonImmutable::parse(Calendario::hoy())->addDays(30)->toDateString();
        $garantias = DB::table('contrato_garantias as g')->join('contratos as c', 'c.id', '=', 'g.contrato_id')->whereNotIn('c.estado', ['anulado', 'culminado', 'liquidado', 'resuelto'])
            ->whereIn('g.estado', ['vigente', 'renovada']);

        return [
            'vigentes' => (string) count($vigentes),
            'monto' => $suma($vigentes, 'monto'),
            'riesgo' => $suma($tarjetas, 'riesgo'),
            'fianzas' => (string) (clone $garantias)->where('g.vencimiento', '<=', $hasta)->count(),
            'garantizado' => bcadd((string) ((clone $garantias)->sum('g.monto') ?: '0'), '0', 2),
        ];
    }

    /** @param  array<string, mixed>  $t */
    public function cumple(array $t, string $filtro): bool
    {
        return match ($filtro) {
            'vigentes' => $t['estado'] === 'vigente',
            'por_completar' => $t['estado'] === 'borrador',
            'por_vencer' => $t['estado'] === 'vigente' && $t['restan'] >= 0 && $t['restan'] <= 30,
            'vencidos' => $t['vencido'],
            'con_penalidad' => $t['con_penalidad'],
            'culminados' => in_array($t['estado'], ['culminado', 'liquidado'], true),
            default => true,
        };
    }

    /**
     * Ordena las tarjetas. Urgencia: borradores, vencidos (el más atrasado primero), carta fianza a 30 días, por días
     * restantes y, al final, los culminados.
     *
     * @param  list<array<string, mixed>>  $tarjetas
     * @return list<array<string, mixed>>
     */
    public function ordenar(array $tarjetas, string $orden): array
    {
        $cerrado = fn (array $t): int => in_array($t['estado'], ['borrador', 'vigente'], true) ? 0 : 1;

        usort($tarjetas, match ($orden) {
            'monto' => fn (array $a, array $b): int => bccomp((string) $b['monto'], (string) $a['monto'], 2),
            'dias' => fn (array $a, array $b): int => [$cerrado($a), $a['restan']] <=> [$cerrado($b), $b['restan']],
            'atraso' => fn (array $a, array $b): int => [$a['atraso'] === null ? 1 : 0, -($a['atraso'] ?? 0), $cerrado($a)] <=> [$b['atraso'] === null ? 1 : 0, -($b['atraso'] ?? 0), $cerrado($b)],
            'cliente' => fn (array $a, array $b): int => [mb_strtolower($a['cliente']), $a['codigo']] <=> [mb_strtolower($b['cliente']), $b['codigo']],
            default => fn (array $a, array $b): int => $this->urgencia($a) <=> $this->urgencia($b),
        });

        return $tarjetas;
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array{int, int, string}
     */
    private function urgencia(array $t): array
    {
        return match (true) {
            $t['estado'] === 'borrador' => [0, $t['restan'], $t['codigo']],
            $t['vencido'] => [1, $t['restan'], $t['codigo']],
            $t['estado'] === 'vigente' && $t['fianza_30'] => [2, (int) $t['fianza']['dias'], $t['codigo']],
            $t['estado'] === 'vigente' => [3, $t['restan'], $t['codigo']],
            default => [4, -$t['id'], $t['codigo']],
        };
    }

    /**
     * El calendario de vencimientos: fin de plazo de los contratos vigentes y vencimiento de las cartas fianza vigentes,
     * en columnas: Vencidos, Esta semana, las tres siguientes y luego por mes.
     *
     * @return list<array{clave: string, titulo: string, items: list<array<string, mixed>>}>
     */
    public function calendario(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $finSemana = $hoy->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $columnas = ['vencidos' => ['clave' => 'vencidos', 'titulo' => 'Vencidos', 'items' => []], 'semana-0' => ['clave' => 'semana-0', 'titulo' => 'Esta semana', 'items' => []]];

        for ($n = 1; $n <= 3; $n++) {
            $desde = $finSemana->addDays(1 + 7 * ($n - 1));
            $columnas["semana-{$n}"] = ['clave' => "semana-{$n}", 'titulo' => 'Semana del '.$desde->format('d/m'), 'items' => []];
        }

        $eventos = [];

        foreach (Contrato::query()->with('garantias')->whereIn('estado', ['vigente', 'borrador', 'culminado'])->get() as $c) {
            if ($c->estado === 'vigente') {
                $eventos[] = ['tipo' => 'fin', 'fecha' => $this->contratos->fin($c), 'contrato_id' => $c->id, 'codigo' => $c->codigo, 'texto' => 'Fin de plazo', 'monto' => $this->contratos->montoVigente($c)];
            }

            foreach ($c->garantias->whereIn('estado', ['vigente', 'renovada']) as $g) {
                $eventos[] = ['tipo' => 'fianza', 'fecha' => $g->vencimiento, 'contrato_id' => $c->id, 'codigo' => $c->codigo, 'texto' => 'Carta fianza · '.$g->banco, 'monto' => bcadd((string) $g->monto, '0', 2)];
            }
        }

        usort($eventos, fn (array $a, array $b): int => [$a['fecha']->toDateString(), $a['codigo']] <=> [$b['fecha']->toDateString(), $b['codigo']]);

        foreach ($eventos as $e) {
            $f = $e['fecha'];
            $e['dias'] = (int) $hoy->diffInDays($f, false);
            $e['cuando'] = $e['dias'] < 0 ? 'venció hace '.self::dias(-$e['dias']) : ($e['dias'] === 0 ? 'vence hoy' : $f->format('d/m/Y'));

            if ($f->lt($hoy)) {
                $clave = 'vencidos';
            } elseif ($f->lte($finSemana)) {
                $clave = 'semana-0';
            } elseif ($f->lte($finSemana->addDays(21))) {
                $clave = 'semana-'.(intdiv((int) $finSemana->diffInDays($f) - 1, 7) + 1);
            } else {
                $clave = $f->format('Y-m');
                $columnas[$clave] ??= ['clave' => $clave, 'titulo' => self::MESES[(int) $f->format('n')].' '.$f->format('Y'), 'items' => []];
            }

            $columnas[$clave]['items'][] = $e;
        }

        return array_values($columnas);
    }

    /**
     * Los avisos para Mien: carta fianza a 30 días, contrato que vence en 7 días (o ya venció) y contrato atrasado
     * contra su plazo.
     *
     * @return array{fianzas: list<array<string, mixed>>, plazos: list<array<string, mixed>>, atrasos: list<array<string, mixed>>}
     */
    public function avisos(): array
    {
        $avisos = ['fianzas' => [], 'plazos' => [], 'atrasos' => []];

        foreach ($this->ordenar($this->tarjetas(), 'urgencia') as $t) {
            $url = '/contratos/'.$t['id'];

            if ($t['fianza_30'] && $t['estado'] !== 'borrador') {
                $d = (int) $t['fianza']['dias'];
                $avisos['fianzas'][] = ['texto' => "La carta fianza de {$t['codigo']} ".($d < 0 ? 'venció hace '.self::dias(-$d) : ($d === 0 ? 'vence hoy' : 'vence en '.self::dias($d))),
                    'nota' => $t['fianza']['banco'].' · S/ '.number_format((float) $t['fianza']['monto'], 2), 'url' => $url, 'tono' => $d <= 15 ? 'danger' : 'warning'];
            }

            if ($t['estado'] === 'vigente' && $t['restan'] <= 7) {
                $avisos['plazos'][] = ['texto' => $t['restan'] < 0 ? "El contrato {$t['codigo']} venció hace ".self::dias(-$t['restan']) : "El contrato {$t['codigo']} vence en ".self::dias($t['restan']),
                    'nota' => bccomp($t['riesgo'], '0', 2) > 0 ? 'Penalidad en riesgo S/ '.number_format((float) $t['riesgo'], 2) : null, 'url' => $url, 'tono' => $t['restan'] < 0 ? 'danger' : 'warning'];
            }

            if ($t['atrasado']) {
                $avisos['atrasos'][] = ['texto' => "{$t['codigo']} va atrasado {$t['atraso']} puntos contra su plazo", 'nota' => "Plazo {$t['tiempo_pct']} % · avance {$t['avance_pct']} %", 'url' => $url, 'tono' => 'warning'];
            }
        }

        return $avisos;
    }

    public static function dias(int $n): string
    {
        return $n === 1 ? '1 día' : "{$n} días";
    }

    /** Redondeo al entero, mitad hacia arriba, sin pasar por float. */
    public static function entero(string $x): int
    {
        return (int) (str_starts_with($x, '-') ? bcsub($x, '0.5', 0) : bcadd($x, '0.5', 0));
    }

    private function textoPlazo(string $estado, int $restan): string
    {
        return match (true) {
            $estado === 'borrador' => 'Por activar · '.self::dias(max(0, $restan)).' de plazo',
            $estado !== 'vigente' => Contrato::ESTADOS[$estado],
            $restan < 0 => 'vencido hace '.self::dias(-$restan),
            $restan === 0 => 'vence hoy',
            default => ($restan === 1 ? '1 día restante' : "{$restan} días restantes"),
        };
    }

    /**
     * El semáforo de la carta fianza: verde vigente, ámbar a 60 días o menos, coral a 30 o vencida, gris sin carta.
     *
     * @return array{tono: string, texto: string, dias: ?int, banco: ?string, monto: ?string, vence: ?string}
     */
    private function fianza(Contrato $c, CarbonImmutable $hoy): array
    {
        $g = $this->contratos->fianzaVigente($c);

        if ($g === null) {
            $solicitada = $c->garantias()->where('estado', 'solicitada')->exists();

            return ['tono' => 'gris', 'texto' => $solicitada ? 'Carta fianza solicitada' : 'Sin carta fianza', 'dias' => null, 'banco' => null, 'monto' => null, 'vence' => null];
        }

        $d = (int) $hoy->diffInDays($g->vencimiento, false);

        return ['tono' => $d <= 30 ? 'coral' : ($d <= 60 ? 'ambar' : 'verde'),
            'texto' => 'Carta fianza '.($d < 0 ? 'vencida hace '.self::dias(-$d) : ($d <= 60 ? 'vence en '.self::dias($d) : 'vence el '.$g->vencimiento->format('d/m/Y'))),
            'dias' => $d, 'banco' => $g->banco, 'monto' => bcadd((string) $g->monto, '0', 2), 'vence' => $g->vencimiento->format('d/m/Y')];
    }

    /**
     * Los hitos sobre una línea del inicio al fin, con su posición en % y si están cumplidos, vencidos o por venir.
     *
     * @return array{puntos: list<array<string, mixed>>, hoy: ?int, proximo: ?string}
     */
    private function hitos(Contrato $c, CarbonImmutable $hoy): array
    {
        $hitos = $c->hitos;
        $inicio = $hitos->map(fn (HitoContrato $h): CarbonImmutable => $h->fecha_prevista)->push($c->fecha_inicio)->min();
        $fin = $hitos->map(fn (HitoContrato $h): CarbonImmutable => $h->fecha_prevista)->push($this->contratos->fin($c))->max();
        $total = max(1, (int) $inicio->diffInDays($fin));
        $pos = fn (CarbonImmutable $f): int => intdiv(max(0, min($total, (int) $inicio->diffInDays($f, false))) * 100, $total);
        $cortos = ['Firma del contrato' => 'Firma', 'Entrega de equipos' => 'Entrega', 'Instalación' => 'Instalación', 'Conformidad' => 'Conformidad'];
        $proximo = $hitos->first(fn (HitoContrato $h): bool => $h->fecha_real === null);

        return [
            'puntos' => $hitos->map(fn (HitoContrato $h): array => ['nombre' => $h->nombre, 'corto' => $cortos[$h->nombre] ?? $h->nombre, 'pos' => $pos($h->fecha_prevista),
                'fecha' => $h->fecha_prevista->format('d/m/Y'),
                'estado' => $h->fecha_real !== null ? 'cumplido' : ($h->fecha_prevista->lt($hoy) ? 'vencido' : 'pendiente')])->values()->all(),
            'hoy' => in_array($c->estado, ['borrador', 'vigente'], true) && $hoy->gte($inicio) ? $pos($hoy) : null,
            'proximo' => $proximo === null ? null : ($cortos[$proximo->nombre] ?? $proximo->nombre).' · '.$proximo->fecha_prevista->format('d/m'),
        ];
    }
}
