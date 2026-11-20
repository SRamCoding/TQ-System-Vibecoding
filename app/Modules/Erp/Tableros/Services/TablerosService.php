<?php

declare(strict_types=1);

namespace App\Modules\Erp\Tableros\Services;

use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * MVP rápido (ficha t8-tableros): el tablero de inicio de cada rol (v4, parte 5). Cada uno responde pocas preguntas: cuatro
 * números arriba y tres listas de «qué vence», «dónde se va» y «qué espera algo de mí». Los números salen de los mismos
 * servicios que usan las pantallas de cada módulo.
 *
 * @phpstan-type Tablero array{kpis: list<array{etiqueta: string, valor: string, nota: string, tono?: string}>, listas: list<array{titulo: string, items: list<array{texto: string, nota?: string, url?: string, tono?: string}>}>}
 */
final readonly class TablerosService
{
    /** Rol => [nombre, permiso que lo muestra]. */
    public const array ROLES = [
        'jefatura' => ['Jefatura de Proyectos', 'erp.contratos.ver'],
        'almacen' => ['Almacén', 'erp.compras.ver'],
        'tesoreria' => ['Tesorería', 'erp.finanzas.ver'],
        'comercial' => ['Comercial', 'crm.licitaciones.ver'],
        'gerencia' => ['Gerencia General', 'erp.finanzas.ver'],
    ];

    private function hoy(): CarbonImmutable
    {
        return CarbonImmutable::parse(Calendario::hoy());
    }

    private static function s(string|float|int|null $m): string
    {
        return 'S/ '.number_format((float) ($m ?? 0), 2);
    }

    /** @return list<object> */
    private function proyectosEnEjecucion(?int $jefeUserId = null): array
    {
        return DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('e.codigo', 'en_ejecucion')
            ->when($jefeUserId !== null, fn ($q) => $q->where('p.jefe_proyecto_user_id', $jefeUserId))->orderBy('p.codigo')->get(['p.*'])->all();
    }

    /** @return Tablero */
    public function jefatura(?int $jefeUserId = null): array
    {
        $hoy = $this->hoy();
        $proyectos = $this->proyectosEnEjecucion($jefeUserId);
        $ids = array_map(fn ($p): int => $p->id, $proyectos);
        $ct = app(ContratosService::class);
        $contratos = Contrato::query()->whereIn('proyecto_id', $ids)->where('estado', 'vigente')->get();
        $enContratos = $contratos->reduce(fn (string $a, Contrato $c): string => bcadd($a, $ct->montoVigente($c), 2), '0');
        $hitos = DB::table('contrato_hitos as h')->join('contratos as c', 'c.id', '=', 'h.contrato_id')->join('proyectos as p', 'p.id', '=', 'c.proyecto_id')->whereIn('c.proyecto_id', $ids)
            ->whereNull('h.fecha_real')->where('h.fecha_prevista', '<=', $hoy->addDays(14)->toDateString())->orderBy('h.fecha_prevista')->get(['h.*', 'p.nombre as proyecto', 'p.id as pid']);
        $excedidas = [];

        foreach ($proyectos as $p) {
            foreach (app(DineroDelProyecto::class)->de($p->id)['partidas'] ?? [] as $pt) {
                if (bccomp($pt['saldo']->presupuestado, '0', 2) > 0 && bccomp($pt['saldo']->comprometido, bcmul($pt['saldo']->presupuestado, '0.9', 2), 2) >= 0) {
                    $excedidas[] = ['texto' => "{$p->codigo} · {$pt['codigo']} {$pt['nombre']}", 'nota' => bcmul(bcdiv($pt['saldo']->comprometido, $pt['saldo']->presupuestado, 4), '100', 0).' % comprometido', 'url' => "/proyectos/{$p->id}",
                        'tono' => $pt['saldo']->excedida() ? 'danger' : 'warning'];
                }
            }
        }

        $atrasados = $contratos->filter(fn (Contrato $c): bool => $ct->fin($c)->lt($hoy));
        $riesgo = $atrasados->reduce(fn (string $a, Contrato $c): string => bcadd($a, bcmul($ct->penalidadDiaria($c), (string) $ct->fin($c)->diffInDays($hoy), 2), 2), '0');
        $tareos = DB::table('tareos as t')->join('proyectos as p', 'p.id', '=', 't.proyecto_id')->whereIn('t.proyecto_id', $ids)->where('t.estado', 'abierto')->where('t.semana', '<', $hoy->startOfWeek()->toDateString())->get(['t.semana', 'p.codigo']);
        $tareosAbiertos = DB::table('tareos as t')->join('proyectos as p', 'p.id', '=', 't.proyecto_id')->whereIn('t.proyecto_id', $ids)->where('t.estado', 'abierto')->get(['t.semana', 'p.codigo']);
        $reqs = DB::table('requerimientos')->whereIn('proyecto_id', $ids)->where('estado', 'por_aprobar')->count();
        $sinResp = DB::table('partidas_control')->whereIn('proyecto_id', $ids)->where('es_hoja', true)->where('es_activa', true)->whereNull('responsable_user_id')->count();
        $garantias = DB::table('contrato_garantias as g')->join('contratos as c', 'c.id', '=', 'g.contrato_id')->whereIn('c.proyecto_id', $ids)->whereIn('g.estado', ['vigente', 'renovada'])
            ->whereBetween('g.vencimiento', [$hoy->toDateString(), $hoy->addDays(30)->toDateString()])->get(['g.*', 'c.codigo']);

        $espera = [];
        foreach ($tareosAbiertos as $t) {
            $espera[] = ['texto' => 'Tareo de la semana del '.CarbonImmutable::parse($t->semana)->format('d/m')." sin cerrar · {$t->codigo}", 'url' => '/tareo', 'tono' => CarbonImmutable::parse($t->semana)->lt($hoy->startOfWeek()) ? 'danger' : null];
        }
        if ($reqs > 0) {
            $espera[] = ['texto' => "{$reqs} requerimientos por aprobar", 'url' => '/requerimientos'];
        }
        if ($sinResp > 0) {
            $espera[] = ['texto' => "{$sinResp} partidas sin responsable", 'url' => '/proyectos'];
        }
        foreach ($garantias as $g) {
            $espera[] = ['texto' => "Carta fianza de {$g->codigo} vence el ".CarbonImmutable::parse($g->vencimiento)->format('d/m/Y').': renovarla', 'url' => "/contratos/{$g->contrato_id}", 'tono' => 'warning'];
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Mis proyectos activos', 'valor' => (string) count($proyectos), 'nota' => self::s($enContratos).' en contratos'],
                ['etiqueta' => 'Hitos en 14 días', 'valor' => (string) $hitos->count(), 'nota' => $hitos->where('fecha_prevista', '<', $hoy->toDateString())->count().' vencidos', 'tono' => $hitos->where('fecha_prevista', '<', $hoy->toDateString())->isNotEmpty() ? 'danger' : null],
                ['etiqueta' => 'Partidas al límite', 'valor' => (string) count($excedidas), 'nota' => 'con 90 % o más comprometido', 'tono' => count($excedidas) > 0 ? 'warning' : null],
                ['etiqueta' => 'Penalidad en riesgo', 'valor' => self::s($riesgo), 'nota' => $atrasados->isEmpty() ? 'ningún contrato atrasado' : $atrasados->count().' contratos pasados de plazo', 'tono' => $atrasados->isEmpty() ? 'success' : 'danger'],
            ],
            'listas' => [
                ['titulo' => 'Qué vence', 'items' => $hitos->map(fn ($h): array => ['texto' => "{$h->nombre} · {$h->proyecto}", 'nota' => ($h->fecha_prevista < $hoy->toDateString() ? 'vencido el ' : '').CarbonImmutable::parse($h->fecha_prevista)->format('d/m/Y'),
                    'url' => "/contratos/{$h->contrato_id}", 'tono' => $h->fecha_prevista < $hoy->toDateString() ? 'danger' : null])->values()->all()],
                ['titulo' => 'Dónde se va el costo', 'items' => $excedidas],
                ['titulo' => 'Esperan algo de mí', 'items' => $espera],
            ],
        ];
    }

    /** @return Tablero */
    public function almacen(): array
    {
        $hoy = $this->hoy();
        $activos = array_map(fn ($p): int => $p->id, $this->proyectosEnEjecucion());
        $porRecibir = DB::table('ordenes')->where('clase', 'compra')->whereIn('estado', ['aprobada', 'enviada', 'recibida_parcial'])->where(fn ($q) => $q->whereNull('proyecto_id')->orWhereIn('proyecto_id', $activos))->orderBy('entrega_pactada')->get();
        $antiguas = $porRecibir->filter(fn ($o): bool => $o->entrega_pactada !== null && $o->entrega_pactada < $hoy->subDays(30)->toDateString());
        $porRecibir = $porRecibir->diffKeys($antiguas)->values();
        $hoyRec = DB::table('recepciones')->where('fecha', $hoy->toDateString())->count();
        $despachos = DB::table('requerimientos as r')->leftJoin('proyectos as p', 'p.id', '=', 'r.proyecto_id')->where('r.estado', 'aprobado')->orderBy('r.fecha_necesidad')->get(['r.*', 'p.codigo as proyecto']);
        $valor = DB::table('stock')->selectRaw('COALESCE(SUM(cantidad * costo_promedio), 0) v')->value('v');
        $series = DB::table('series')->where('estado', 'en_almacen')->count();
        $movs = DB::table('movimientos_almacen as m')->join('productos as x', 'x.id', '=', 'm.producto_id')->orderByDesc('m.fecha')->orderByDesc('m.id')->limit(6)->get(['m.*', 'x.descripcion_corta', 'x.descripcion']);

        return [
            'kpis' => [
                ['etiqueta' => 'Por recibir', 'valor' => (string) $porRecibir->count(), 'nota' => $porRecibir->filter(fn ($o): bool => $o->entrega_pactada !== null && $o->entrega_pactada < $hoy->toDateString())->count().' atrasadas · '.$antiguas->count().' con saldo de hace más de 30 días'],
                ['etiqueta' => 'Recibido hoy', 'valor' => (string) $hoyRec, 'nota' => 'recepciones'],
                ['etiqueta' => 'Despachos pendientes', 'valor' => (string) $despachos->count(), 'nota' => 'requerimientos aprobados'],
                ['etiqueta' => 'Inventario', 'valor' => self::s($valor), 'nota' => "{$series} series en almacén"],
            ],
            'listas' => [
                ['titulo' => 'Qué entra', 'items' => $porRecibir->take(8)->map(fn ($o): array => ['texto' => "{$o->codigo} · {$o->proveedor_nombre}", 'nota' => $o->entrega_pactada ? 'pactada '.CarbonImmutable::parse($o->entrega_pactada)->format('d/m') : 'sin fecha pactada',
                    'url' => '/ordenes', 'tono' => $o->entrega_pactada !== null && $o->entrega_pactada < $hoy->toDateString() ? 'danger' : null])->values()->all()],
                ['titulo' => 'Qué sale', 'items' => $despachos->map(fn ($r): array => ['texto' => "{$r->codigo} · {$r->descripcion}", 'nota' => ($r->proyecto ?? '').($r->fecha_necesidad ? ' · antes del '.CarbonImmutable::parse($r->fecha_necesidad)->format('d/m') : ''), 'url' => '/requerimientos'])->values()->all()],
                ['titulo' => 'Últimos movimientos', 'items' => $movs->map(fn ($m): array => ['texto' => ucfirst((string) $m->tipo).' · '.($m->descripcion_corta ?: $m->descripcion), 'nota' => rtrim(rtrim((string) $m->cantidad, '0'), '.').' · '.CarbonImmutable::parse($m->fecha)->format('d/m'), 'url' => '/almacen'])->values()->all()],
            ],
        ];
    }

    /** @return Tablero */
    public function tesoreria(): array
    {
        $hoy = $this->hoy();
        $finSemana = $hoy->endOfWeek()->toDateString();
        $semana = DB::table('comprobantes')->where('clase', 'compra')->where('estado', 'programado')->where('pago_programado', '<=', $finSemana)->get();
        $porProgramar = DB::table('comprobantes')->where('clase', 'compra')->where('estado', 'por_programar')->get();
        $vencidas = $porProgramar->filter(fn ($c): bool => $c->vencimiento !== null && $c->vencimiento < $hoy->toDateString());
        $observadas = DB::table('comprobantes')->where('clase', 'compra')->whereNotIn('estado', ['pagado', 'anulado'])->where('cruce', 'like', '%dif%')->count();
        $caja = DB::table('movimientos_caja')->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) s")->value('s');
        $sinConciliar = DB::table('movimientos_caja')->where('conciliado', false)->count();
        $cobrar = DB::table('comprobantes')->where('clase', 'venta')->where('estado', 'por_cobrar')->orderBy('vencimiento')->get();
        $flujo = app(FinanzasService::class)->flujo($hoy->format('Y-m'), 3);
        $minimo = collect($flujo)->sortBy(fn (array $f): float => (float) $f['neto'])->first();
        $garantias = DB::table('contrato_garantias as g')->join('contratos as c', 'c.id', '=', 'g.contrato_id')->whereIn('g.estado', ['vigente', 'renovada'])->orderBy('g.vencimiento')->get(['g.*', 'c.codigo']);

        return [
            'kpis' => [
                ['etiqueta' => 'Pagos esta semana', 'valor' => (string) $semana->count(), 'nota' => self::s($semana->sum('saldo'))],
                ['etiqueta' => 'Facturas por programar', 'valor' => (string) $porProgramar->count(), 'nota' => $vencidas->count().' vencidas · '.$observadas.' con diferencia', 'tono' => $vencidas->isNotEmpty() ? 'danger' : null],
                ['etiqueta' => 'Caja', 'valor' => self::s($caja), 'nota' => "{$sinConciliar} movimientos sin conciliar", 'tono' => (float) $caja < 0 ? 'danger' : null],
                ['etiqueta' => 'Cartas fianza vigentes', 'valor' => (string) $garantias->count(), 'nota' => $garantias->filter(fn ($g): bool => $g->vencimiento <= $hoy->addDays(30)->toDateString())->count().' vencen en 30 días'],
            ],
            'listas' => [
                ['titulo' => 'Qué pago', 'items' => $semana->merge($vencidas)->map(fn ($c): array => ['texto' => "{$c->serie_numero} · {$c->razon_social}", 'nota' => self::s($c->saldo).' · '.($c->estado === 'programado' ? 'programado '.CarbonImmutable::parse($c->pago_programado)->format('d/m') : 'venció '.CarbonImmutable::parse($c->vencimiento)->format('d/m')),
                    'url' => '/tablero-pagos', 'tono' => $c->estado === 'programado' ? null : 'danger'])->values()->all()],
                ['titulo' => 'Qué cobro', 'items' => $cobrar->map(fn ($c): array => ['texto' => "{$c->serie_numero} · {$c->razon_social}", 'nota' => self::s($c->saldo).' · vence '.CarbonImmutable::parse($c->vencimiento)->format('d/m/Y'), 'url' => '/cobranzas',
                    'tono' => $c->vencimiento < $hoy->toDateString() ? 'danger' : null])->values()->all()],
                ['titulo' => 'La caja de los próximos meses', 'items' => array_map(fn (array $f): array => ['texto' => $f['mes'].($f['proyectado'] ? ' (proyectado)' : ''), 'nota' => 'entra '.self::s($f['ingresos']).' · sale '.self::s($f['egresos']).' · neto '.self::s($f['neto']),
                    'url' => '/flujo-caja', 'tono' => $minimo !== null && $f['mes'] === $minimo['mes'] && (float) $f['neto'] < 0 ? 'danger' : null], $flujo)],
            ],
        ];
    }

    /** @return Tablero */
    public function comercial(?int $ejecutivoUserId = null): array
    {
        $hoy = $this->hoy();
        $abiertas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->join('cuentas as c', 'c.id', '=', 'o.cuenta_id')->join('parties as y', 'y.id', '=', 'c.party_id')
            ->whereIn('e.codigo', ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion'])->when($ejecutivoUserId !== null, fn ($q) => $q->where('o.ejecutivo_user_id', $ejecutivoUserId))
            ->orderBy('o.fecha_cierre_estimada')->get(['o.*', 'e.nombre as etapa', 'y.razon_social as cuenta']);
        $ganadas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'ganada')->whereYear('o.fecha_cierre_real', $hoy->year)->sum('o.monto_estimado');
        $hitos = DB::table('licitacion_hitos as h')->join('licitaciones as l', 'l.id', '=', 'h.licitacion_id')->whereIn('l.estado', ['registrada', 'en_preparacion', 'presentada'])->where('h.es_cumplido', false)
            ->where('h.fecha', '<=', $hoy->addDays(14)->toDateString())->orderBy('h.fecha')->get(['h.*', 'l.codigo', 'l.id as lid']);
        $nuevasSeace = DB::table('convocatorias_seace')->where('estado', 'nueva')->count();
        $actividades = DB::table('actividades')->where('es_realizada', false)->when($ejecutivoUserId !== null, fn ($q) => $q->where('responsable_user_id', $ejecutivoUserId))
            ->where('fecha', '<=', $hoy->addDays(7)->endOfDay())->orderBy('fecha')->limit(8)->get();
        $sinActividad = $abiertas->filter(fn ($o): bool => ! DB::table('actividades')->where('oportunidad_id', $o->id)->where('fecha', '>=', $hoy->subDays(30))->exists());

        return [
            'kpis' => [
                ['etiqueta' => 'Oportunidades abiertas', 'valor' => (string) $abiertas->count(), 'nota' => self::s($abiertas->sum('monto_estimado')).' en juego'],
                ['etiqueta' => 'Ganado este año', 'valor' => self::s($ganadas), 'nota' => 'oportunidades ganadas en '.$hoy->year],
                ['etiqueta' => 'Licitaciones: hitos en 14 días', 'valor' => (string) $hitos->count(), 'nota' => "{$nuevasSeace} convocatorias nuevas del SEACE"],
                ['etiqueta' => 'Sin contacto en 30 días', 'valor' => (string) $sinActividad->count(), 'nota' => 'oportunidades abiertas', 'tono' => $sinActividad->isNotEmpty() ? 'warning' : null],
            ],
            'listas' => [
                ['titulo' => 'Qué cierra pronto', 'items' => $abiertas->take(8)->map(fn ($o): array => ['texto' => "{$o->nombre} · {$o->cuenta}", 'nota' => $o->etapa.' · '.self::s($o->monto_estimado).($o->fecha_cierre_estimada ? ' · cierre '.CarbonImmutable::parse($o->fecha_cierre_estimada)->format('d/m') : ''),
                    'url' => "/oportunidades/{$o->id}", 'tono' => $o->fecha_cierre_estimada !== null && $o->fecha_cierre_estimada < $hoy->toDateString() ? 'danger' : null])->values()->all()],
                ['titulo' => 'Licitaciones que vencen', 'items' => $hitos->map(fn ($h): array => ['texto' => "{$h->codigo} · {$h->etapa}", 'nota' => CarbonImmutable::parse($h->fecha)->format('d/m/Y'), 'url' => "/licitaciones/{$h->lid}", 'tono' => $h->fecha < $hoy->toDateString() ? 'danger' : null])->values()->all()],
                ['titulo' => 'Mi agenda de la semana', 'items' => $actividades->map(fn ($a): array => ['texto' => ucfirst((string) $a->tipo).' · '.$a->asunto, 'nota' => CarbonImmutable::parse($a->fecha)->format('d/m H:i'), 'url' => '/agenda', 'tono' => $a->fecha < $hoy->toDateString() ? 'danger' : null])->values()->all()],
            ],
        ];
    }

    /** @return Tablero */
    public function gerencia(): array
    {
        $hoy = $this->hoy();
        $ct = app(ContratosService::class);
        $fin = app(FinanzasService::class);
        $proyectos = $this->proyectosEnEjecucion();
        $contratos = Contrato::query()->where('estado', 'vigente')->get();
        $enEjecucion = $contratos->reduce(fn (string $a, Contrato $c): string => bcadd($a, $ct->montoVigente($c), 2), '0');
        $margenes = [];
        $vendido = '0';
        $previsto = '0';

        foreach ($proyectos as $p) {
            $d = app(DineroDelProyecto::class)->de($p->id);

            if ($d !== null && $d['venta'] !== null && bccomp($d['venta'], '0', 2) > 0) {
                $m = $d['margen_proyectado'] ?? $d['margen_previsto'];
                $vendido = bcadd($vendido, $d['venta'], 2);
                $previsto = bcadd($previsto, (string) $m, 2);
                $margenes[] = ['texto' => "{$p->codigo} · {$p->nombre}", 'pct' => (float) bcmul(bcdiv((string) $m, $d['venta'], 6), '100', 1), 'url' => "/proyectos/{$p->id}"];
            }
        }

        usort($margenes, fn (array $a, array $b): int => $a['pct'] <=> $b['pct']);
        $ponderado = bccomp($vendido, '0', 2) > 0 ? bcmul(bcdiv($previsto, $vendido, 6), '100', 1) : '0';
        $atrasados = $contratos->filter(fn (Contrato $c): bool => $ct->fin($c)->lt($hoy));
        $riesgo = $atrasados->reduce(fn (string $a, Contrato $c): string => bcadd($a, bcmul($ct->penalidadDiaria($c), (string) $ct->fin($c)->diffInDays($hoy), 2), 2), '0');
        $viejas = DB::table('comprobantes')->where('clase', 'venta')->where('estado', 'por_cobrar')->where('emision', '<', $hoy->subDays(60)->toDateString());
        $flujo = $fin->flujo($hoy->format('Y-m'), 4);
        $firmas = DB::table('ordenes')->where('estado', 'por_aprobar')->count() + DB::table('requerimientos')->where('estado', 'por_aprobar')->count();
        $porCotizar = DB::table('cotizaciones as c')->join('estados as e', 'e.id', '=', 'c.estado_id')->where('e.codigo', 'por_aprobar')->get(['c.*']);

        return [
            'kpis' => [
                ['etiqueta' => 'Contratos en ejecución', 'valor' => self::s($enEjecucion), 'nota' => $contratos->count().' contratos vigentes'],
                ['etiqueta' => 'Margen ponderado', 'valor' => $ponderado.' %', 'nota' => 'proyectado sobre lo vendido en ejecución', 'tono' => (float) $ponderado < 10 ? 'warning' : 'success'],
                ['etiqueta' => 'Penalidad en riesgo', 'valor' => self::s($riesgo), 'nota' => $atrasados->count().' contratos pasados de plazo', 'tono' => $atrasados->isEmpty() ? 'success' : 'danger'],
                ['etiqueta' => 'Por cobrar +60 días', 'valor' => self::s((clone $viejas)->sum('saldo')), 'nota' => (clone $viejas)->distinct()->count('ruc').' clientes', 'tono' => (clone $viejas)->exists() ? 'warning' : null],
            ],
            'listas' => [
                ['titulo' => 'Margen por proyecto', 'items' => array_map(fn (array $m): array => ['texto' => $m['texto'], 'nota' => number_format($m['pct'], 1).' %', 'url' => $m['url'], 'tono' => $m['pct'] < 0 ? 'danger' : ($m['pct'] < 10 ? 'warning' : 'success')], $margenes)],
                ['titulo' => 'Caja', 'items' => array_map(fn (array $f): array => ['texto' => $f['mes'].($f['proyectado'] ? ' (proyectado)' : ''), 'nota' => 'neto '.self::s($f['neto']), 'url' => '/flujo-caja', 'tono' => (float) $f['neto'] < 0 ? 'danger' : null], $flujo)],
                ['titulo' => 'Esperan mi firma', 'items' => [
                    ...$porCotizar->map(fn ($c): array => ['texto' => "Cotización {$c->numero} por aprobar", 'url' => '/cotizaciones'])->values()->all(),
                    ...($firmas > 0 ? [['texto' => "{$firmas} órdenes y requerimientos por aprobar", 'url' => '/mis-aprobaciones']] : []),
                    ...$atrasados->map(fn (Contrato $c): array => ['texto' => "{$c->codigo} pasado de plazo desde el ".$ct->fin($c)->format('d/m/Y'), 'nota' => 'decidir penalidad o ampliación', 'url' => "/contratos/{$c->id}", 'tono' => 'danger'])->values()->all(),
                ]],
            ],
        ];
    }
}
