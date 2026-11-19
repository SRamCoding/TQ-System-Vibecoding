<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
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
            'cierre-vencido' => $consulta->whereIn('oportunidades.estado_id', $this->estados(array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases())))
                ->where('oportunidades.fecha_cierre_estimada', '<', Calendario::hoy()),
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
            'cierre' => $consulta->orderBy('oportunidades.fecha_cierre_estimada')->orderBy('oportunidades.id'),
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
        $opciones = ['atencion' => [], 'etapas' => [], 'ejecutivos' => [], 'lineas' => [], 'clientes' => []];

        foreach (FiltrosOportunidades::ATENCION as $clave => $etiqueta) {
            $opciones['atencion'][$clave] = ['etiqueta' => $etiqueta, 'cantidad' => $this->enAtencion($this->filtrarVitrina($filtros, 'atencion'), $clave)->count()];
        }

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

        return $opciones;
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
}
