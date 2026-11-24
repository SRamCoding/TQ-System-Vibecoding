<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Lo que leen la portada de la lista y el tablero de oportunidades (plan 2.21, D11): pestañas, avisos con «Ver
 * cuáles», tarjetas del embudo y las columnas del tablero. Todo filtra en la base. Las anuladas no cuentan en las
 * tarjetas, el ponderado ni el tablero: solo salen en su pestaña y en «Todas» (D11.2).
 */
final readonly class PortadaOportunidades
{
    /** Pestañas, en su orden. */
    public const array PESTANAS = ['abiertas', 'mias', 'estancadas', 'ganadas', 'perdidas', 'anuladas', 'todas'];

    /** Avisos, en su orden; cada uno es también un valor del filtro «Revisar» de la tabla. */
    public const array AVISOS = ['estancadas', 'ejecutivo-inactivo'];

    public function __construct(
        private OportunidadesService $oportunidades,
        private ParametrosService $parametros,
    ) {}

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function enPestana(Builder $consulta, string $pestana, ?User $usuario): Builder
    {
        return match ($pestana) {
            'abiertas' => $consulta->abiertas(),
            // Las que atiendo, sin las anuladas; sin sesión no hay «mías».
            'mias' => $usuario === null ? $consulta->whereRaw('false') : $this->sinAnuladas($consulta)->where('oportunidades.ejecutivo_user_id', $usuario->id),
            'estancadas' => $this->estancadas($consulta),
            'ganadas' => $this->enEstado($consulta, 'ganada'),
            'perdidas' => $this->enEstado($consulta, 'perdida'),
            'anuladas' => $this->enEstado($consulta, 'anulada'),
            default => $consulta,
        };
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function enAviso(Builder $consulta, string $aviso): Builder
    {
        return match ($aviso) {
            'estancadas' => $this->estancadas($consulta),
            'ejecutivo-inactivo' => $consulta->abiertas()->whereIn('oportunidades.ejecutivo_user_id', User::query()->select('id')->where('es_activo', false)),
            default => $consulta,
        };
    }

    /**
     * Los avisos con alguna oportunidad, con su cantidad.
     *
     * @return array<string, int>
     */
    public function avisos(): array
    {
        $avisos = [];

        foreach (self::AVISOS as $aviso) {
            $cantidad = $this->enAviso(Oportunidad::query(), $aviso)->count();

            if ($cantidad > 0) {
                $avisos[$aviso] = $cantidad;
            }
        }

        return $avisos;
    }

    /**
     * Abiertas que pasan los días máximos de su etapa (D2; 0 es «sin máximo»).
     *
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function estancadas(Builder $consulta): Builder
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $conMaximo = array_filter(EtapaAbierta::cases(), fn (EtapaAbierta $e): bool => $this->diasMaximos($e) > 0);

        if ($conMaximo === []) {
            return $consulta->whereRaw('false');
        }

        return $consulta->where(function (Builder $o) use ($conMaximo, $hoy): void {
            foreach ($conMaximo as $etapa) {
                $o->orWhere(fn (Builder $q): Builder => $this->enEstado($q, $etapa->value)
                    ->where('oportunidades.en_etapa_desde', '<', $hoy->subDays($this->diasMaximos($etapa))->toDateString()));
            }
        });
    }

    public function diasMaximos(EtapaAbierta $etapa): int
    {
        return $this->parametros->entero($etapa->parametroDiasMaximos());
    }

    public function probabilidad(EtapaAbierta $etapa): int
    {
        return $this->parametros->entero($etapa->parametroProbabilidad());
    }

    /** Días desde que entró en su etapa actual. */
    public function diasEnEtapa(Oportunidad $oportunidad): int
    {
        return (int) $oportunidad->en_etapa_desde->diffInDays(CarbonImmutable::parse(Calendario::hoy()));
    }

    /** Si pasa los días máximos de su etapa (solo una abierta, y solo si la etapa tiene máximo). */
    public function pasaElMaximo(Oportunidad $oportunidad, string $codigoEtapa): bool
    {
        $etapa = EtapaAbierta::tryFrom($codigoEtapa);
        $maximo = $etapa === null ? 0 : $this->diasMaximos($etapa);

        return $maximo > 0 && $this->diasEnEtapa($oportunidad) > $maximo;
    }

    /**
     * Las tarjetas (D11.1): el embudo total y su ponderado, y las que cierran este mes. Solo abiertas.
     *
     * @return array{embudo: EmbudoEnSoles, esteMes: EmbudoEnSoles}
     */
    public function tarjetas(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return [
            'embudo' => $this->oportunidades->embudo(Oportunidad::query()),
            'esteMes' => $this->oportunidades->embudo(Oportunidad::query()->whereBetween('oportunidades.fecha_cierre_estimada', [
                $hoy->startOfMonth()->toDateString(), $hoy->endOfMonth()->toDateString(),
            ])),
        ];
    }

    /**
     * Las cerradas que muestra la columna «Cierre» del tablero (D11.3): ganadas y perdidas con cierre real en el período
     * en curso del parámetro (mes, trimestre o año).
     *
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function cerradasDelPeriodo(Builder $consulta): Builder
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        [$desde, $hasta] = match ($this->parametros->texto(OportunidadesServiceProvider::PARAMETRO_PERIODO_CIERRE)) {
            'trimestre' => [$hoy->startOfQuarter(), $hoy->endOfQuarter()],
            'anio' => [$hoy->startOfYear(), $hoy->endOfYear()],
            default => [$hoy->startOfMonth(), $hoy->endOfMonth()],
        };

        return $consulta
            ->whereIn('oportunidades.estado_id', $this->estados(['ganada', 'perdida']))
            ->whereBetween('oportunidades.fecha_cierre_real', [$desde->toDateString(), $hasta->toDateString()]);
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function enEstado(Builder $consulta, string $codigo): Builder
    {
        return $consulta->whereIn('oportunidades.estado_id', $this->estados([$codigo]));
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function sinAnuladas(Builder $consulta): Builder
    {
        return $consulta->whereNotIn('oportunidades.estado_id', $this->estados(['anulada']));
    }

    /**
     * La lista de la vista «Tarjetas» (plan 2.21d, D11), filtrada en la base y sin anuladas. `$salvo` deja fuera un grupo
     * para contar sus opciones con los demás filtros puestos.
     *
     * @return Builder<Oportunidad>
     */
    public function filtrarVitrina(FiltrosOportunidades $filtros, ?string $salvo = null): Builder
    {
        $consulta = $this->sinAnuladas(Oportunidad::query());
        $texto = trim($filtros->texto);

        if ($texto !== '') {
            $cuentas = array_map(fn (ResumenCuenta $c): int => $c->id, app(CuentasService::class)->buscar($texto, 200));
            $consulta->where(fn (Builder $q) => $q->whereRaw('unaccent(oportunidades.nombre) ilike unaccent(?)', ["%{$texto}%"])
                ->orWhere('oportunidades.numero', 'ilike', "%{$texto}%")
                ->orWhereIn('oportunidades.cuenta_id', $cuentas));
        }

        if ($salvo !== 'etapas' && $filtros->etapas !== []) {
            $consulta->whereIn('oportunidades.estado_id', $this->estados($filtros->etapas));
        }

        if ($salvo !== 'ejecutivos' && $filtros->ejecutivos !== []) {
            $consulta->whereIn('oportunidades.ejecutivo_user_id', $filtros->ejecutivos);
        }

        if ($salvo !== 'lineas' && $filtros->lineas !== []) {
            $consulta->whereIn('oportunidades.linea_solucion_id', $filtros->lineas);
        }

        if ($salvo !== 'clientes' && $filtros->clientes !== []) {
            $tipos = $this->tipoDeCliente();
            $consulta->whereIn('oportunidades.cuenta_id', array_keys(array_filter($tipos, fn (string $t): bool => in_array($t, $filtros->clientes, true))));
        }

        if ($salvo !== 'tipos' && $filtros->tipos !== []) {
            $this->enTipos($consulta, $filtros->tipos);
        }

        // Observación 9.3: por el canal del lead del que nació la oportunidad.
        if ($salvo !== 'origenes' && $filtros->origenes !== []) {
            $consulta->whereIn('oportunidades.id', array_keys(array_filter($this->canalesDeOportunidades(), fn (string $c): bool => in_array($c, $filtros->origenes, true))));
        }

        if ($salvo !== 'atencion') {
            foreach ($filtros->atencion as $clave) {
                $this->enAtencion($consulta, $clave);
            }
        }

        return $consulta;
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function enAtencion(Builder $consulta, string $clave): Builder
    {
        return match ($clave) {
            'atascadas' => $this->estancadas($consulta),
            // Observación 7.1: un plazo de la licitación vencido cuenta como un cierre vencido.
            'cierre-vencido' => $consulta->whereIn('oportunidades.estado_id', $this->estados(array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases())))
                ->where(fn (Builder $q) => $q->where('oportunidades.fecha_cierre_estimada', '<', Calendario::hoy())
                    ->orWhereIn('oportunidades.id', array_keys(array_filter($this->licitaciones(), fn (array $l): bool => ($l['hito']['dias'] ?? 0) < 0)))),
            default => $consulta,
        };
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    public function ordenarVitrina(Builder $consulta, string $orden): Builder
    {
        return match ($orden) {
            // Observación 7.1: por urgencia, la fecha más cercana entre el cierre estimado y el próximo plazo de su licitación.
            'cierre' => $this->ordenPorUrgencia($consulta),
            'dias' => $consulta->orderBy('oportunidades.en_etapa_desde')->orderBy('oportunidades.id'),
            'recientes' => $consulta->orderByDesc('oportunidades.id'),
            default => $consulta->orderByDesc('oportunidades.monto_estimado')->orderByDesc('oportunidades.id'),
        };
    }

    /**
     * Las opciones de cada grupo con cuántas tendrían si se marcaran, con los demás filtros puestos.
     *
     * @return array<string, array<int|string, array{etiqueta: string, cantidad: int}>>
     */
    public function opcionesVitrina(FiltrosOportunidades $filtros): array
    {
        $opciones = ['atencion' => [], 'tipos' => [], 'etapas' => [], 'ejecutivos' => [], 'lineas' => [], 'clientes' => [], 'origenes' => []];

        // Observación 7.1: los números junto a cada filtro también cuentan las tarjetas por calificar.
        foreach (FiltrosOportunidades::ATENCION as $clave => $etiqueta) {
            $extra = count(array_filter($this->porCalificar($filtros, 'atencion'), fn (array $t): bool => in_array($clave, $t['atencion'], true)));
            $opciones['atencion'][$clave] = ['etiqueta' => $etiqueta, 'cantidad' => $this->enAtencion($this->filtrarVitrina($filtros, 'atencion'), $clave)->count() + $extra];
        }

        $porCalificarSinTipo = $this->porCalificar($filtros, 'tipos');

        foreach (FiltrosOportunidades::TIPOS as $clave => $etiqueta) {
            $extra = count(array_filter($porCalificarSinTipo, fn (array $t): bool => $t['tipo'] === $clave));
            $cantidad = $this->enTipos($this->filtrarVitrina($filtros, 'tipos'), [$clave])->count() + $extra;

            if ($clave !== 'licitacion_privada' || $cantidad > 0) {
                $opciones['tipos'][$clave] = ['etiqueta' => $etiqueta, 'cantidad' => $cantidad];
            }
        }

        $opciones['etapas'][TallaDeGema::POR_CALIFICAR] = ['etiqueta' => 'Por calificar', 'cantidad' => count($this->porCalificar($filtros, 'etapas'))];

        $porEtapa = $this->filtrarVitrina($filtros, 'etapas')->join('estados', 'estados.id', '=', 'oportunidades.estado_id')
            ->groupBy('estados.codigo')->selectRaw('estados.codigo, count(*) as n')->pluck('n', 'codigo');

        foreach ((new DefinicionOportunidad)->estados() as $estado) {
            if ($estado->codigo !== 'anulada') {
                $opciones['etapas'][$estado->codigo] = ['etiqueta' => $estado->nombre, 'cantidad' => (int) ($porEtapa[$estado->codigo] ?? 0)];
            }
        }

        $porEjecutivo = $this->filtrarVitrina($filtros, 'ejecutivos')->groupBy('oportunidades.ejecutivo_user_id')
            ->selectRaw('oportunidades.ejecutivo_user_id as quien, count(*) as n')->pluck('n', 'quien');
        $ejecutivos = $this->sinAnuladas(Oportunidad::query())->distinct()->pluck('ejecutivo_user_id')->all();

        foreach (User::query()->whereKey($ejecutivos)->orderBy('name')->get(['id', 'name']) as $quien) {
            $opciones['ejecutivos'][$quien->id] = ['etiqueta' => $quien->name, 'cantidad' => (int) ($porEjecutivo[$quien->id] ?? 0)];
        }

        $porLinea = $this->filtrarVitrina($filtros, 'lineas')->groupBy('oportunidades.linea_solucion_id')
            ->selectRaw('oportunidades.linea_solucion_id as linea, count(*) as n')->pluck('n', 'linea');

        foreach (app(CatalogosService::class)->paraFiltro(Catalogo::LineasSolucion) as $id => $nombre) {
            $opciones['lineas'][(int) $id] = ['etiqueta' => (string) $nombre, 'cantidad' => (int) ($porLinea[$id] ?? 0)];
        }

        $tipos = $this->tipoDeCliente();
        $etiquetas = $this->etiquetasDeCliente();
        $porCuenta = $this->filtrarVitrina($filtros, 'clientes')->groupBy('oportunidades.cuenta_id')
            ->selectRaw('oportunidades.cuenta_id as cuenta, count(*) as n')->pluck('n', 'cuenta');

        foreach ($etiquetas as $tipo => $etiqueta) {
            $cantidad = 0;

            foreach ($porCuenta as $cuenta => $n) {
                $cantidad += ($tipos[(int) $cuenta] ?? null) === $tipo ? (int) $n : 0;
            }

            $opciones['clientes'][$tipo] = ['etiqueta' => $etiqueta, 'cantidad' => $cantidad];
        }

        // Observación 9.3: «Origen», el canal de cada lead (y de la oportunidad que nació de uno). Solo los que tienen algo.
        $porCalificarSinOrigen = $this->porCalificar($filtros, 'origenes');
        $deOportunidades = $this->canalesDeOportunidades();
        $idsVisibles = $this->filtrarVitrina($filtros, 'origenes')->pluck('oportunidades.id')->all();

        foreach (app(LeadsService::class)->canales() as $canal) {
            $cantidad = count(array_filter($porCalificarSinOrigen, fn (array $t): bool => ($t['canal']['codigo'] ?? null) === $canal['codigo']))
                + count(array_filter($idsVisibles, fn (int $id): bool => ($deOportunidades[$id] ?? null) === $canal['codigo']));

            if ($cantidad > 0 || in_array($canal['codigo'], $filtros->origenes, true)) {
                $opciones['origenes'][$canal['codigo']] = ['etiqueta' => $canal['nombre'], 'cantidad' => $cantidad, 'icono' => $canal['icono']];
            }
        }

        return $opciones;
    }

    /**
     * Observación 7.1: lo que está «Por calificar» (los leads abiertos y las licitaciones por decidir), como tarjetas,
     * con los filtros puestos (salvo el grupo `$salvo`, para contar sus opciones). Sin filtro de etapa, o con «Por
     * calificar» marcado, aparecen; con otra etapa sola, no.
     *
     * @return list<array<string, mixed>>
     */
    public function porCalificar(FiltrosOportunidades $filtros, ?string $salvo = null): array
    {
        if ($salvo !== 'etapas' && $filtros->etapas !== [] && ! in_array(TallaDeGema::POR_CALIFICAR, $filtros->etapas, true)) {
            return [];
        }

        $items = once(function (): array {
            $licitaciones = app(LicitacionesService::class)->porDecidir();
            $cuentas = app(CuentasService::class)->resumenesDe(array_values(array_unique(array_column($licitaciones, 'cuenta_id'))));
            $items = [];

            foreach ($licitaciones as $l) {
                $c = $cuentas[$l['cuenta_id']] ?? null;
                $items[] = [
                    'clase' => 'licitacion', 'id' => $l['id'], 'clave' => "lic-{$l['id']}", 'nombre' => $l['objeto'], 'entidad' => $c->etiqueta ?? '—',
                    'origen' => $l['nomenclatura'], 'origenEtiqueta' => ($l['deSeace'] ?? false) ? 'Convocatoria del SEACE' : 'Registrada a mano', 'detalle' => null, 'monto' => $l['valor_referencial'], 'montoEtiqueta' => 'Valor referencial',
                    'tipo' => $l['es_publica'] ? 'licitacion_publica' : 'licitacion_privada', 'hito' => $l['hito'], 'desde' => $l['desde'],
                    'ejecutivo' => $l['responsable'], 'linea' => $l['linea_solucion_id'],
                    'cliente' => $c === null ? null : (! $c->esPublica ? 'privado' : ($c->nivelGobiernoId === null ? 'publica' : "nivel:{$c->nivelGobiernoId}")),
                    'atencion' => ($l['hito']['dias'] ?? 0) < 0 ? ['cierre-vencido'] : [],
                    'fecha' => $l['hito']['fecha'] ?? null,
                ];
            }

            foreach (app(LeadsService::class)->porCalificar() as $l) {
                $items[] = [
                    'clase' => 'lead', 'id' => $l['id'], 'clave' => "lead-{$l['id']}", 'nombre' => $l['organizacion'] === null ? $l['nombre'] : "{$l['nombre']} · {$l['organizacion']}",
                    'entidad' => $l['organizacion'] ?? $l['nombre'], 'origen' => $l['campania'] === null ? $l['origen'] : "{$l['origen']} · {$l['campania']}",
                    'origenEtiqueta' => $l['campania'] === null ? $l['origen'] : "Campaña {$l['campania']}",
                    'detalle' => $l['mensaje'], 'monto' => null, 'montoEtiqueta' => null, 'tipo' => 'privada', 'hito' => null, 'desde' => $l['desde'],
                    'dias' => $l['dias'], 'sinAtender' => $l['sinAtender'], 'ejecutivo' => $l['asignado'], 'linea' => null, 'cliente' => null,
                    'atencion' => $l['sinAtender'] ? ['atascadas'] : [], 'fecha' => null, 'canal' => $l['canal'] ?? null,
                ];
            }

            return $items;
        });

        $texto = mb_strtolower(trim(Str::ascii($filtros->texto)));

        return array_values(array_filter($items, function (array $t) use ($filtros, $salvo, $texto): bool {
            if ($texto !== '' && ! str_contains(mb_strtolower(Str::ascii("{$t['nombre']} {$t['entidad']} {$t['origen']}")), $texto)) {
                return false;
            }
            if ($salvo !== 'tipos' && $filtros->tipos !== [] && ! in_array($t['tipo'], $filtros->tipos, true)) {
                return false;
            }
            if ($salvo !== 'ejecutivos' && $filtros->ejecutivos !== [] && ! in_array($t['ejecutivo'], $filtros->ejecutivos, true)) {
                return false;
            }
            if ($salvo !== 'lineas' && $filtros->lineas !== [] && ! in_array($t['linea'], $filtros->lineas, true)) {
                return false;
            }
            if ($salvo !== 'clientes' && $filtros->clientes !== [] && ! in_array($t['cliente'], $filtros->clientes, true)) {
                return false;
            }
            if ($salvo !== 'atencion' && array_diff($filtros->atencion, $t['atencion']) !== []) {
                return false;
            }
            if ($salvo !== 'origenes' && $filtros->origenes !== [] && ! in_array($t['canal']['codigo'] ?? null, $filtros->origenes, true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Observación 7.1: la licitación de cada oportunidad que nació de una (pública o privada y su próximo plazo).
     *
     * @return array<int, array{licitacion_id: int, es_publica: bool, hito: ?array{etapa: string, fecha: CarbonImmutable, dias: int}}>
     */
    public function licitaciones(): array
    {
        return once(fn (): array => app(LicitacionesService::class)->deOportunidades());
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @param  list<string>  $tipos
     * @return Builder<Oportunidad>
     */
    private function enTipos(Builder $consulta, array $tipos): Builder
    {
        $lic = $this->licitaciones();
        $publicas = array_keys(array_filter($lic, fn (array $l): bool => $l['es_publica']));
        $privadas = array_keys(array_filter($lic, fn (array $l): bool => ! $l['es_publica']));

        return $consulta->where(function (Builder $q) use ($tipos, $lic, $publicas, $privadas): void {
            if (in_array('privada', $tipos, true)) {
                $q->orWhereNotIn('oportunidades.id', array_keys($lic) === [] ? [0] : array_keys($lic));
            }
            if (in_array('licitacion_publica', $tipos, true)) {
                $q->orWhereIn('oportunidades.id', $publicas);
            }
            if (in_array('licitacion_privada', $tipos, true)) {
                $q->orWhereIn('oportunidades.id', $privadas);
            }
        });
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @return Builder<Oportunidad>
     */
    private function ordenPorUrgencia(Builder $consulta): Builder
    {
        $casos = [];
        $valores = [];

        foreach ($this->licitaciones() as $id => $l) {
            if ($l['hito'] !== null) {
                $casos[] = 'when ? then least(oportunidades.fecha_cierre_estimada, ?::date)';
                array_push($valores, $id, $l['hito']['fecha']->toDateString());
            }
        }

        $expresion = $casos === [] ? 'oportunidades.fecha_cierre_estimada' : 'case oportunidades.id '.implode(' ', $casos).' else oportunidades.fecha_cierre_estimada end';

        // Las cerradas (ganadas o perdidas) al final: ya no apuran.
        return $consulta->orderByRaw('case when oportunidades.estado_id in (select id from estados where documento_type = ? and codigo in (?, ?)) then 1 else 0 end', [(new DefinicionOportunidad)->tipo(), 'ganada', 'perdida'])
            ->orderByRaw("{$expresion} asc nulls last", $valores)->orderBy('oportunidades.id');
    }

    /**
     * El tipo de cliente de cada cuenta con oportunidades, por `CuentasService` (C de Cuentas): `privado`, `nivel:<id>`
     * o `publica` (pública sin nivel).
     *
     * @return array<int, string>
     */
    private function tipoDeCliente(): array
    {
        return array_map(fn (ResumenCuenta $c): string => ! $c->esPublica ? 'privado' : ($c->nivelGobiernoId === null ? 'publica' : "nivel:{$c->nivelGobiernoId}"), $this->resumenesDeCuentas());
    }

    /** @return array<string, string> */
    private function etiquetasDeCliente(): array
    {
        $etiquetas = [];

        foreach ($this->resumenesDeCuentas() as $c) {
            $clave = ! $c->esPublica ? 'privado' : ($c->nivelGobiernoId === null ? 'publica' : "nivel:{$c->nivelGobiernoId}");
            $etiquetas[$clave] = ! $c->esPublica ? 'Privado' : ($c->nivelGobiernoNombre === null ? 'Entidad pública' : "Gobierno {$c->nivelGobiernoNombre}");
        }

        asort($etiquetas);

        return $etiquetas;
    }

    /** @return array<int, ResumenCuenta> */
    private function resumenesDeCuentas(): array
    {
        return once(fn (): array => app(CuentasService::class)->resumenesDe(
            $this->sinAnuladas(Oportunidad::query())->distinct()->pluck('cuenta_id')->map(fn (mixed $id): int => (int) $id)->all(),
        ));
    }

    /**
     * @param  list<string>  $codigos
     * @return Builder<Estado>
     */
    private function estados(array $codigos): Builder
    {
        return Estado::query()->select('id')->where('documento_type', (new DefinicionOportunidad)->tipo())->whereIn('codigo', $codigos);
    }

    /** @return array<int, string> id de la oportunidad → código del canal de su lead (observación 9.3) */
    private function canalesDeOportunidades(): array
    {
        return once(fn (): array => app(LeadsService::class)->canalesDeOportunidades());
    }
}
