<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Services;

use App\Modules\Erp\Personal\Models\Tareo;
use App\Modules\Erp\Personal\Models\TareoLinea;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Observaciones 6.9 y 6.10 (MV-069, MV-070, ficha t4-personal-tareo): el tareo que se rellena solo y su cuadrícula.
 * Cada persona tiene su jornada (horas por día); una semana nueva trae a la gente asignada al proyecto con su jornada por
 * su dedicación, repartida por igual en sus días, en la partida de su última semana en ese proyecto; los feriados van en
 * 0 y a quien tiene el SCTR vencido no se le rellena. Lo propuesto queda marcado «de la jornada» hasta que alguien lo
 * toca o se cierra la semana.
 */
final readonly class TareoService
{
    /** Más horas que estas en un día (sumando sus líneas) son horas extra. */
    public const string HORAS_DEL_DIA = '8';

    public const array DIAS = PersonalService::DIAS;

    /** @return array<string, string> la jornada que se propone al registrar a alguien (planilla: 48 h, la máxima legal) */
    public static function jornadaPropuesta(string $vinculo): array
    {
        return $vinculo === 'planilla'
            ? ['lu' => '8', 'ma' => '8', 'mi' => '8', 'ju' => '8', 'vi' => '8', 'sa' => '4', 'do' => '0']
            : array_fill_keys(array_keys(self::DIAS), '0');
    }

    /** @return array<string, string> día => horas */
    public static function jornadaDe(object|array $c): array
    {
        $c = (object) $c;
        $j = is_string($c->jornada ?? null) ? json_decode($c->jornada, true) : ($c->jornada ?? null);
        $j = is_array($j) ? $j : self::jornadaPropuesta((string) ($c->vinculo ?? 'eventual'));

        return collect(self::DIAS)->map(fn (string $e, string $d): string => self::limpio((string) ($j[$d] ?? '0')))->all();
    }

    /** @param  array<string, string>  $j */
    public static function totalJornada(array $j): string
    {
        return self::limpio(array_reduce($j, fn (string $s, string $h): string => bcadd($s, $h, 1), '0'));
    }

    /** «Lu–Vi 8 h · Sá 4 h · 48 h», o «Sin jornada». @param  array<string, string>  $j */
    public static function textoJornada(array $j): string
    {
        $grupos = [];

        foreach ($j as $d => $h) {
            if (bccomp($h, '0', 1) === 0) {
                $grupos[] = null;

                continue;
            }

            $ultimo = $grupos === [] ? null : $grupos[array_key_last($grupos)];

            if ($ultimo !== null && $ultimo['h'] === $h) {
                $grupos[array_key_last($grupos)]['hasta'] = $d;
            } else {
                $grupos[] = ['desde' => $d, 'hasta' => $d, 'h' => $h];
            }
        }

        $partes = array_map(fn (array $g): string => self::DIAS[$g['desde']].($g['hasta'] !== $g['desde'] ? '–'.self::DIAS[$g['hasta']] : '')." {$g['h']} h", array_values(array_filter($grupos)));

        return $partes === [] ? 'Sin jornada' : implode(' · ', $partes).' · '.self::totalJornada($j).' h';
    }

    public function cambiarJornada(int $colaboradorId, array $horas): void
    {
        $j = [];

        foreach (array_keys(self::DIAS) as $d) {
            $h = self::limpio((string) ($horas[$d] ?? '0'));

            if (bccomp($h, '0', 1) < 0 || bccomp($h, '16', 1) > 0) {
                throw new RuntimeException('Las horas de un día van de 0 a 16.');
            }

            $j[$d] = $h;
        }

        DB::table('colaboradores')->where('id', $colaboradorId)->update(['jornada' => json_encode($j), 'updated_at' => now()]);
    }

    /** @return array<string, string> día => nombre del feriado (los que Techquk no trabaja) */
    public function feriados(CarbonImmutable $lunes): array
    {
        $fechas = [];

        foreach (array_keys(self::DIAS) as $i => $d) {
            $fechas[$lunes->addDays($i)->toDateString()] = $d;
        }

        return DB::table('feriados')->whereIn('codigo', array_keys($fechas))->where('es_activo', true)->where('techquk_no_trabaja', true)
            ->pluck('nombre', 'codigo')->mapWithKeys(fn (string $n, string $f): array => [$fechas[$f] => $n])->all();
    }

    /** @return array<int, int> colaborador => su dedicación (%) a este proyecto esa semana */
    public function asignados(int $proyectoId, CarbonImmutable $lunes): array
    {
        return DB::table('asignaciones as a')->join('colaboradores as c', 'c.id', '=', 'a.colaborador_id')->where('c.es_activo', true)->where('a.proyecto_id', $proyectoId)
            ->where('a.desde', '<=', $lunes->addDays(6)->toDateString())->where(fn ($q) => $q->whereNull('a.hasta')->orWhere('a.hasta', '>=', $lunes->toDateString()))
            ->groupBy('a.colaborador_id')->selectRaw('a.colaborador_id, SUM(a.porcentaje) AS p')->pluck('p', 'a.colaborador_id')->map(fn ($p): int => (int) $p)->all();
    }

    public static function habilitado(object $c, CarbonImmutable $lunes): bool
    {
        return $c->habilitado_hasta !== null && CarbonImmutable::parse($c->habilitado_hasta)->gte($lunes);
    }

    /** La partida de su última semana en el proyecto (donde más horas tuvo), o nula. */
    public function partidaAnterior(int $proyectoId, int $colaboradorId, CarbonImmutable $lunes): ?int
    {
        $id = DB::table('tareo_lineas as l')->join('tareos as t', 't.id', '=', 'l.tareo_id')->where('t.proyecto_id', $proyectoId)->where('l.colaborador_id', $colaboradorId)
            ->where('t.semana', '<', $lunes->toDateString())->whereNotNull('l.partida_control_id')
            ->orderByDesc('t.semana')->orderByRaw('(l.lu + l.ma + l.mi + l.ju + l.vi + l.sa + l.do) DESC')->value('l.partida_control_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Su jornada por su dedicación, repartida por igual en sus días (60 % de 48 h → 28.8 h → 4.8 h en cada uno de 6 días);
     * los feriados quedan en 0.
     *
     * @param  array<string, string>  $jornada
     * @param  array<string, string>  $feriados
     * @return array<string, string>
     */
    public static function propuesta(array $jornada, int $porcentaje, array $feriados): array
    {
        $horas = array_fill_keys(array_keys(self::DIAS), '0');
        $dias = array_keys(array_filter($jornada, fn (string $h): bool => bccomp($h, '0', 1) > 0));

        if ($dias === []) {
            return $horas;
        }

        $total = bcdiv(bcmul(self::totalJornada($jornada), (string) $porcentaje, 2), '100', 2);
        $cada = bcdiv($total, (string) count($dias), 1);
        $resto = bcsub($total, bcmul($cada, (string) (count($dias) - 1), 2), 1);

        foreach ($dias as $i => $d) {
            $horas[$d] = $i === count($dias) - 1 ? $resto : $cada;
        }

        foreach (array_keys($feriados) as $d) {
            $horas[$d] = '0';
        }

        return array_map(self::limpio(...), $horas);
    }

    /** Rellena con la jornada a la gente asignada cuyas líneas nadie tocó. Devuelve cuántas personas propuso. */
    public function rellenar(int $tareoId): int
    {
        $t = Tareo::query()->findOrFail($tareoId);
        $this->abierta($t);
        $feriados = $this->feriados($t->semana);
        $cuantas = 0;

        foreach ($this->asignados((int) $t->proyecto_id, $t->semana) as $colaboradorId => $porcentaje) {
            $c = DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')->where('c.id', $colaboradorId)->first(['c.*', 'k.costo_hora']);

            if (! self::habilitado($c, $t->semana) || TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $colaboradorId)->where('de_jornada', false)->exists()) {
                continue;
            }

            TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $colaboradorId)->delete();
            TareoLinea::query()->create(['tareo_id' => $t->id, 'colaborador_id' => $colaboradorId, 'partida_control_id' => $this->partidaAnterior((int) $t->proyecto_id, $colaboradorId, $t->semana),
                'costo_hora' => $c->costo_hora, 'de_jornada' => true, ...self::propuesta(self::jornadaDe($c), $porcentaje, $feriados)]);
            $cuantas++;
        }

        return $cuantas;
    }

    /** Trae las personas, partidas y horas de la semana anterior del proyecto (sin feriados ni SCTR vencido). */
    public function copiarAnterior(int $tareoId): int
    {
        $t = Tareo::query()->findOrFail($tareoId);
        $this->abierta($t);
        $antes = Tareo::query()->where('proyecto_id', $t->proyecto_id)->where('semana', '<', $t->semana->toDateString())->orderByDesc('semana')->first();

        if ($antes === null) {
            throw new RuntimeException('No hay una semana anterior en este proyecto.');
        }

        $feriados = $this->feriados($t->semana);
        $limpiados = [];
        $cuantas = 0;

        foreach ($antes->lineas as $l) {
            $c = DB::table('colaboradores')->where('id', $l->colaborador_id)->first();

            if ($c === null || ! $c->es_activo || ! self::habilitado($c, $t->semana)
                || TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $l->colaborador_id)->where('partida_control_id', $l->partida_control_id)->where('de_jornada', false)->exists()) {
                continue;
            }

            if (! isset($limpiados[$l->colaborador_id])) {
                TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $l->colaborador_id)->where('de_jornada', true)->delete();
                $limpiados[$l->colaborador_id] = true;
            }

            $horas = collect(self::DIAS)->map(fn (string $e, string $d): string => isset($feriados[$d]) ? '0' : (string) $l->{$d})->all();
            TareoLinea::query()->updateOrCreate(['tareo_id' => $t->id, 'colaborador_id' => $l->colaborador_id, 'partida_control_id' => $l->partida_control_id],
                ['costo_hora' => $l->costo_hora, 'de_jornada' => false, 'faltas' => null, ...$horas]);
            $cuantas++;
        }

        return $cuantas;
    }

    /** Guarda una celda (al salir de ella): la línea deja de ser «de la jornada». */
    public function celda(int $lineaId, string $dia, string $horas): void
    {
        $l = $this->lineaEditable($lineaId);
        $this->dia($dia);
        $h = self::limpio(trim($horas) === '' ? '0' : str_replace(',', '.', trim($horas)));

        if (! is_numeric($h) || bccomp($h, '0', 1) < 0 || bccomp($h, '16', 1) > 0) {
            throw new RuntimeException('Las horas de un día van de 0 a 16.');
        }

        $faltas = array_values(array_diff($l->faltas ?? [], bccomp($h, '0', 1) > 0 ? [$dia] : []));
        $l->update([$dia => $h, 'de_jornada' => false, 'faltas' => $faltas === [] ? null : $faltas]);
    }

    /** Llenar la fila: las horas del primer día con horas, en los demás días de su jornada (sin feriados). */
    public function llenarFila(int $lineaId): void
    {
        $l = $this->lineaEditable($lineaId);
        $t = Tareo::query()->findOrFail($l->tareo_id);
        $origen = collect(array_keys(self::DIAS))->first(fn (string $d): bool => bccomp((string) $l->{$d}, '0', 1) > 0);

        if ($origen === null) {
            throw new RuntimeException('Escribe primero las horas de un día.');
        }

        $jornada = self::jornadaDe(DB::table('colaboradores')->where('id', $l->colaborador_id)->first());
        $feriados = $this->feriados($t->semana);
        $cambios = ['de_jornada' => false];

        foreach ($jornada as $d => $h) {
            if (bccomp($h, '0', 1) > 0 && ! isset($feriados[$d]) && ! in_array($d, $l->faltas ?? [], true)) {
                $cambios[$d] = (string) $l->{$origen};
            }
        }

        $l->update($cambios);
    }

    public function cambiarPartida(int $lineaId, int $partidaId): void
    {
        $l = $this->lineaEditable($lineaId);
        $t = Tareo::query()->findOrFail($l->tareo_id);
        $this->partidaDelProyecto($partidaId, (int) $t->proyecto_id);

        if (TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $l->colaborador_id)->where('partida_control_id', $partidaId)->whereKeyNot($l->id)->exists()) {
            throw new RuntimeException('Ya tiene una línea en esa partida.');
        }

        $l->update(['partida_control_id' => $partidaId, 'de_jornada' => false]);
    }

    public function quitarFila(int $lineaId): void
    {
        $this->lineaEditable($lineaId)->delete();
    }

    /** Una fila nueva al final: la persona y la partida, sin horas todavía. */
    public function nuevaFila(int $tareoId, int $colaboradorId, int $partidaId): void
    {
        $t = Tareo::query()->findOrFail($tareoId);
        $this->abierta($t);
        $c = DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')->where('c.id', $colaboradorId)->first(['c.*', 'k.costo_hora']);

        if ($c === null || ! self::habilitado($c, $t->semana)) {
            throw new RuntimeException('Esa persona no tiene su SCTR vigente: no se le puede tarear en obra.');
        }

        $this->partidaDelProyecto($partidaId, (int) $t->proyecto_id);

        if (TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $colaboradorId)->where('partida_control_id', $partidaId)->exists()) {
            throw new RuntimeException('Esa persona ya tiene una línea en esa partida.');
        }

        TareoLinea::query()->create(['tareo_id' => $t->id, 'colaborador_id' => $colaboradorId, 'partida_control_id' => $partidaId, 'costo_hora' => $c->costo_hora, 'de_jornada' => false]);
    }

    /**
     * Asistencia del día (celular): «Vino» pone las horas de su jornada de ese día en su primera línea (las demás en 0);
     * «Faltó» deja ese día en 0 en todas y lo marca.
     */
    public function asistencia(int $tareoId, int $colaboradorId, string $dia, bool $vino): void
    {
        $t = Tareo::query()->findOrFail($tareoId);
        $this->abierta($t);
        $this->dia($dia);
        $c = DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')->where('c.id', $colaboradorId)->first(['c.*', 'k.costo_hora']);

        if ($c === null || ! self::habilitado($c, $t->semana)) {
            throw new RuntimeException('Esa persona no tiene su SCTR vigente: no se le puede tarear en obra.');
        }

        $lineas = TareoLinea::query()->where('tareo_id', $t->id)->where('colaborador_id', $colaboradorId)->orderBy('id')->get();

        if ($lineas->isEmpty()) {
            $lineas = collect([TareoLinea::query()->create(['tareo_id' => $t->id, 'colaborador_id' => $colaboradorId, 'partida_control_id' => $this->partidaAnterior((int) $t->proyecto_id, $colaboradorId, $t->semana),
                'costo_hora' => $c->costo_hora, 'de_jornada' => false])]);
        }

        $horas = self::jornadaDe($c)[$dia];
        $horas = bccomp($horas, '0', 1) > 0 ? $horas : self::HORAS_DEL_DIA;

        foreach ($lineas as $i => $l) {
            $faltas = array_values(array_diff($l->faltas ?? [], [$dia]));

            if (! $vino && $i === 0) {
                $faltas[] = $dia;
            }

            $l->update([$dia => $vino && $i === 0 ? $horas : '0', 'de_jornada' => false, 'faltas' => $faltas === [] ? null : $faltas]);
        }
    }

    /**
     * Todo lo que muestra la pantalla del tareo de un proyecto y semana (MV-070).
     *
     * @return array<string, mixed>
     */
    public function tablero(int $proyectoId, CarbonImmutable $lunes, string $agrupar = 'persona'): array
    {
        $t = Tareo::query()->where('proyecto_id', $proyectoId)->where('semana', $lunes->toDateString())->first();
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $feriados = $this->feriados($lunes);
        $asignados = $this->asignados($proyectoId, $lunes);
        $abierta = $t !== null && $t->estado === 'abierto';
        $lineas = $t === null ? collect() : TareoLinea::query()->where('tareo_id', $t->id)->orderBy('id')->get();
        $ids = array_values(array_unique([...array_keys($asignados), ...$lineas->pluck('colaborador_id')->all()]));
        $gente = DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')->whereIn('c.id', $ids ?: [0])->get(['c.*', 'k.nombre as categoria'])->keyBy('id');
        $partidas = DB::table('partidas_control')->whereIn('id', $lineas->pluck('partida_control_id')->filter()->unique()->values()->all() ?: [0])->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $dias = array_keys(self::DIAS);
        $conDomingo = $lineas->contains(fn (TareoLinea $l): bool => bccomp((string) $l->do, '0', 1) > 0)
            || $gente->contains(fn (object $c): bool => bccomp(self::jornadaDe($c)['do'], '0', 1) > 0);
        $visibles = $conDomingo ? $dias : array_slice($dias, 0, 6);

        // Lo que suma cada persona por día en este proyecto (para horas extra y «¿faltó?»).
        $porPersonaDia = [];

        foreach ($lineas as $l) {
            foreach ($dias as $d) {
                $porPersonaDia[$l->colaborador_id][$d] = bcadd($porPersonaDia[$l->colaborador_id][$d] ?? '0', (string) $l->{$d}, 1);
            }
        }

        // La persona en todos sus proyectos esa semana.
        $enTodos = DB::table('tareo_lineas as l')->join('tareos as t', 't.id', '=', 'l.tareo_id')->join('proyectos as p', 'p.id', '=', 't.proyecto_id')
            ->where('t.semana', $lunes->toDateString())->whereIn('l.colaborador_id', $ids ?: [0])
            ->groupBy('l.colaborador_id', 'p.codigo')->selectRaw('l.colaborador_id, p.codigo, SUM(l.lu + l.ma + l.mi + l.ju + l.vi + l.sa + l.do) AS h')->get()->groupBy('colaborador_id');

        $persona = function (int $id) use ($gente, $lunes, $enTodos, $asignados): array {
            $c = $gente[$id];
            $jornada = self::jornadaDe($c);
            $proyectos = ($enTodos[$id] ?? collect())->map(fn ($p): array => ['codigo' => $p->codigo, 'horas' => self::limpio((string) $p->h)])->values()->all();
            $semana = self::limpio(array_reduce($proyectos, fn (string $s, array $p): string => bcadd($s, $p['horas'], 1), '0'));

            return ['id' => $id, 'nombres' => $c->nombres, 'categoria' => $c->categoria, 'avatar' => PersonalService::avatar($c), 'sctr_mal' => ! self::habilitado($c, $lunes),
                'jornada' => $jornada, 'jornada_total' => self::totalJornada($jornada), 'semana_total' => $semana, 'proyectos' => $proyectos,
                'pasa' => bccomp($semana, self::totalJornada($jornada), 1) > 0, 'asignado' => isset($asignados[$id]), 'dedicacion' => $asignados[$id] ?? 0];
        };

        $filas = [];
        $primeraDe = [];

        foreach ($lineas as $l) {
            $p = $persona((int) $l->colaborador_id);
            $esPrimera = ! isset($primeraDe[$l->colaborador_id]);
            $primeraDe[$l->colaborador_id] = true;
            $celdas = [];

            foreach ($visibles as $i => $d) {
                $suma = $porPersonaDia[$l->colaborador_id][$d] ?? '0';
                $celdas[$d] = ['horas' => self::limpio((string) $l->{$d}), 'feriado' => $feriados[$d] ?? null,
                    'extra' => bccomp($suma, self::HORAS_DEL_DIA, 1) > 0 && bccomp((string) $l->{$d}, '0', 1) > 0,
                    'falto' => in_array($d, $l->faltas ?? [], true),
                    'falta_quiza' => $esPrimera && $p['asignado'] && ! $p['sctr_mal'] && bccomp($p['jornada'][$d], '0', 1) > 0 && ! isset($feriados[$d])
                        && bccomp($suma, '0', 1) === 0 && $lunes->addDays($i)->lte($hoy) && ! in_array($d, $l->faltas ?? [], true) && ! $l->de_jornada];
            }

            $filas[] = ['linea' => (int) $l->id, 'persona' => $p, 'primera' => $esPrimera, 'partida' => $l->partida_control_id === null ? null : ['id' => (int) $l->partida_control_id, 'codigo' => $partidas[$l->partida_control_id]->codigo, 'nombre' => $partidas[$l->partida_control_id]->nombre],
                'celdas' => $celdas, 'horas' => self::limpio($l->horas()), 'costo' => $l->costo(), 'de_jornada' => (bool) $l->de_jornada, 'editable' => $abierta && ! $p['sctr_mal']];
        }

        // Quien está asignado con el SCTR vencido y no tiene línea: la fila en coral, sin poder escribir.
        $faltantes = [];

        foreach (array_keys($asignados) as $id) {
            if (! isset($primeraDe[$id]) && ! self::habilitado($gente[$id], $lunes)) {
                $faltantes[] = $persona($id);
            }
        }

        $totalesDia = collect($visibles)->mapWithKeys(fn (string $d): array => [$d => self::limpio($lineas->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, (string) $l->{$d}, 1), '0'))])->all();
        $horas = self::limpio($lineas->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->horas(), 1), '0'));
        $costo = $lineas->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->costo(), 2), '0.00');
        $tareadas = $lineas->filter(fn (TareoLinea $l): bool => bccomp($l->horas(), '0', 1) > 0)->pluck('colaborador_id')->unique()->count();

        return [
            'tareo' => $t === null ? null : ['id' => (int) $t->id, 'estado' => $t->estado], 'abierta' => $abierta, 'lunes' => $lunes, 'dias' => $visibles, 'feriados' => $feriados,
            'filas' => $agrupar === 'persona' ? $filas : [], 'faltantes' => $faltantes, 'totalesDia' => $totalesDia, 'horas' => $horas, 'costo' => $costo,
            'manoDeObra' => $this->manoDeObra($proyectoId, $lunes), 'tareadas' => $tareadas, 'asignadas' => count($asignados),
            'porPartida' => $agrupar === 'partida' ? $this->porPartida($proyectoId, $lunes, $lineas) : [],
            'tira' => $this->tira($proyectoId, $lunes),
            'hoy' => $hoy,
        ];
    }

    /**
     * Mano de obra gastada contra presupuestada del proyecto hasta esa semana: lo gastado es el costo del tareo de todas
     * sus semanas; lo presupuestado, el presupuesto vigente de las partidas donde se tarea (el mismo saldo de Partidas).
     *
     * @return array{gastado: string, presupuestado: string, porcentaje: int}
     */
    public function manoDeObra(int $proyectoId, CarbonImmutable $lunes): array
    {
        $porPartida = $this->gastadoPorPartida($proyectoId, $lunes);
        $saldos = $porPartida === [] ? [] : app(PartidasService::class)->saldos(array_keys($porPartida));
        $presupuestado = array_reduce($saldos, fn (string $s, $saldo): string => bcadd($s, $saldo->presupuestado, 2), '0.00');
        $gastado = array_reduce($porPartida, fn (string $s, string $m): string => bcadd($s, $m, 2), '0.00');

        return ['gastado' => $gastado, 'presupuestado' => $presupuestado, 'porcentaje' => bccomp($presupuestado, '0', 2) > 0 ? (int) round((float) bcdiv(bcmul($gastado, '100', 2), $presupuestado, 2)) : 0];
    }

    /** @return array<int, string> partida => costo del tareo hasta esa semana */
    private function gastadoPorPartida(int $proyectoId, CarbonImmutable $lunes): array
    {
        return DB::table('tareo_lineas as l')->join('tareos as t', 't.id', '=', 'l.tareo_id')->where('t.proyecto_id', $proyectoId)->where('t.semana', '<=', $lunes->toDateString())
            ->whereNotNull('l.partida_control_id')->groupBy('l.partida_control_id')
            ->selectRaw('l.partida_control_id, SUM((l.lu + l.ma + l.mi + l.ju + l.vi + l.sa + l.do) * l.costo_hora) AS m')->pluck('m', 'l.partida_control_id')
            ->map(fn ($m): string => bcadd((string) $m, '0', 2))->all();
    }

    /** @return list<array<string, mixed>> por partida: horas y costo de la semana, y su mano de obra contra lo presupuestado */
    private function porPartida(int $proyectoId, CarbonImmutable $lunes, $lineas): array
    {
        $gastado = $this->gastadoPorPartida($proyectoId, $lunes);
        $ids = array_values(array_unique([...array_keys($gastado), ...$lineas->pluck('partida_control_id')->filter()->all()]));
        $saldos = $ids === [] ? [] : app(PartidasService::class)->saldos($ids);
        $filas = [];

        foreach (DB::table('partidas_control')->whereIn('id', $ids ?: [0])->orderBy('codigo')->get(['id', 'codigo', 'nombre']) as $p) {
            $deLaSemana = $lineas->where('partida_control_id', $p->id);
            $pres = isset($saldos[$p->id]) ? $saldos[$p->id]->presupuestado : '0.00';
            $g = $gastado[$p->id] ?? '0.00';
            $filas[] = ['id' => (int) $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'personas' => $deLaSemana->pluck('colaborador_id')->unique()->count(),
                'horas' => self::limpio($deLaSemana->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->horas(), 1), '0')),
                'costo' => $deLaSemana->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->costo(), 2), '0.00'),
                'gastado' => $g, 'presupuestado' => $pres, 'porcentaje' => bccomp($pres, '0', 2) > 0 ? (int) round((float) bcdiv(bcmul($g, '100', 2), $pres, 2)) : 0];
        }

        $sin = $lineas->whereNull('partida_control_id');

        if ($sin->isNotEmpty()) {
            $filas[] = ['id' => 0, 'codigo' => '—', 'nombre' => 'Sin partida', 'personas' => $sin->pluck('colaborador_id')->unique()->count(),
                'horas' => self::limpio($sin->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->horas(), 1), '0')), 'costo' => $sin->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->costo(), 2), '0.00'),
                'gastado' => '0.00', 'presupuestado' => '0.00', 'porcentaje' => 0];
        }

        return $filas;
    }

    /** @return list<array{lunes: string, etiqueta: string, estado: string, actual: bool}> las semanas del mes de esa semana */
    private function tira(int $proyectoId, CarbonImmutable $lunes): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $mes = $lunes->addDays(3); // el mes de su jueves
        $desde = $mes->startOfMonth()->startOfWeek();
        $estados = DB::table('tareos')->where('proyecto_id', $proyectoId)->whereBetween('semana', [$desde->toDateString(), $mes->endOfMonth()->toDateString()])->pluck('estado', 'semana')
            ->mapWithKeys(fn (string $e, $s): array => [substr((string) $s, 0, 10) => $e])->all();
        $tira = [];

        for ($s = $desde; $s->lte($mes->endOfMonth()); $s = $s->addWeek()) {
            $e = $estados[$s->toDateString()] ?? null;
            $tira[] = ['lunes' => $s->toDateString(), 'etiqueta' => 'Sem '.$s->isoWeek().' · '.$s->format('d/m'), 'actual' => $s->equalTo($lunes),
                'estado' => match (true) {
                    $e === 'cerrado' => 'cerrada',
                    $s->addDays(6)->lt($hoy) && ($e === 'abierto' || $this->asignados($proyectoId, $s) !== []) => 'atrasada',
                    $e === 'abierto' => 'abierta',
                    default => 'sin',
                }];
        }

        return $tira;
    }

    /**
     * Lo que se cargará a cada partida al cerrar y los avisos pendientes.
     *
     * @return array{partidas: list<array<string, string>>, avisos: list<string>, total: string, horas: string}
     */
    public function resumenCierre(int $proyectoId, CarbonImmutable $lunes): array
    {
        $d = $this->tablero($proyectoId, $lunes);
        $lineas = $d['tareo'] === null ? collect() : TareoLinea::query()->where('tareo_id', $d['tareo']['id'])->get();
        $partidas = [];

        foreach ($lineas->filter(fn (TareoLinea $l): bool => bccomp($l->horas(), '0', 1) > 0)->groupBy('partida_control_id') as $id => $ls) {
            $p = $id === '' || $id === null ? null : DB::table('partidas_control')->where('id', $id)->first(['codigo', 'nombre']);
            $partidas[] = ['partida' => $p === null ? 'Sin partida' : "{$p->codigo} · {$p->nombre}", 'horas' => self::limpio($ls->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->horas(), 1), '0')),
                'monto' => $ls->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->costo(), 2), '0.00')];
        }

        $avisos = [];

        foreach ($d['filas'] as $f) {
            foreach ($f['celdas'] as $dia => $c) {
                $nombre = $f['persona']['nombres'].' · '.self::DIAS[$dia];
                if ($c['falto']) {
                    $avisos[] = "Faltó: {$nombre}";
                }
                if ($c['falta_quiza']) {
                    $avisos[] = "¿Faltó? {$nombre} (sin horas)";
                }
                if ($c['extra']) {
                    $avisos[] = "Horas extra: {$nombre} ({$c['horas']} h)";
                }
            }

            if ($f['partida'] === null && bccomp($f['horas'], '0', 1) > 0) {
                $avisos[] = 'Sin partida: '.$f['persona']['nombres'];
            }
        }

        foreach ($d['faltantes'] as $p) {
            $avisos[] = "SCTR vencido: {$p['nombres']} no se tarea";
        }

        return ['partidas' => $partidas, 'avisos' => array_values(array_unique($avisos)), 'total' => $d['costo'], 'horas' => $d['horas']];
    }

    private function lineaEditable(int $lineaId): TareoLinea
    {
        $l = TareoLinea::query()->findOrFail($lineaId);
        $t = Tareo::query()->findOrFail($l->tareo_id);
        $this->abierta($t);

        if (! self::habilitado(DB::table('colaboradores')->where('id', $l->colaborador_id)->first(), $t->semana)) {
            throw new RuntimeException('Su SCTR está vencido: no se le puede tarear en obra.');
        }

        return $l;
    }

    private function abierta(Tareo $t): void
    {
        if ($t->estado !== 'abierto') {
            throw new RuntimeException('La semana está cerrada: se corrige con un ajuste.');
        }
    }

    private function dia(string $dia): void
    {
        if (! isset(self::DIAS[$dia])) {
            throw new RuntimeException('Ese día no existe.');
        }
    }

    private function partidaDelProyecto(int $partidaId, int $proyectoId): void
    {
        if ((int) DB::table('partidas_control')->where('id', $partidaId)->value('proyecto_id') !== $proyectoId) {
            throw new RuntimeException('Esa partida no es de este proyecto.');
        }
    }

    /** «4.80» → «4.8», «8.0» → «8». */
    public static function limpio(string $h): string
    {
        $h = bcadd($h, '0', 1);

        return str_ends_with($h, '.0') ? substr($h, 0, -2) : $h;
    }
}
