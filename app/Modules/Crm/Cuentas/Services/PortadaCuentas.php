<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que lee la portada de la lista de cuentas (plan 2.20, D11): pestañas, avisos con «Ver cuáles», cartera por
 * ejecutivo y últimos cambios. Todo filtra en la base. Nada de lo que aún no existe: sin quien aporte el último contacto,
 * no hay pestaña ni aviso de «Sin actividad».
 */
final readonly class PortadaCuentas
{
    /** Pestañas, en su orden. «Todas» son de verdad todas: activas e inactivas (D11.1). */
    public const array PESTANAS = ['todas', 'mias', 'clientes', 'prospectos', 'nuevas', 'sin-actividad', 'inactivas'];

    /** Avisos, en su orden; cada uno es también un valor del filtro «Revisar» de la tabla. */
    public const array AVISOS = ['sin-actividad', 'ejecutivo-inactivo', 'ficha-inactiva', 'sin-rubro'];

    public function __construct(
        private CuentasService $cuentas,
        private ActividadDeCuentas $actividad,
    ) {}

    /**
     * Las pestañas que existen hoy: sin la de «Sin actividad» mientras nadie aporte el último contacto.
     *
     * @return list<string>
     */
    public function pestanas(): array
    {
        return array_values(array_filter(self::PESTANAS, fn (string $p): bool => $p !== 'sin-actividad' || $this->actividad->tiene(DatoActividad::UltimoContacto)));
    }

    /**
     * Restringe la consulta a una pestaña.
     *
     * @param  Builder<Cuenta>  $consulta
     * @return Builder<Cuenta>
     */
    public function enPestana(Builder $consulta, string $pestana, ?User $usuario): Builder
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return match ($pestana) {
            // Sin sesión no hay «mías».
            'mias' => $usuario === null ? $consulta->whereRaw('false') : $consulta->where('cuentas.ejecutivo_user_id', $usuario->id),
            'clientes' => $this->cuentas->usables($consulta)->whereNotNull('cuentas.cliente_desde'),
            'prospectos' => $this->cuentas->usables($consulta)->whereNull('cuentas.cliente_desde'),
            'nuevas' => $consulta->where('cuentas.created_at', '>=', $hoy->startOfMonth()),
            'sin-actividad' => $this->sinActividad($this->cuentas->usables($consulta)),
            'inactivas' => $consulta->where('cuentas.es_activa', false),
            default => $consulta,
        };
    }

    /**
     * Restringe la consulta a lo que señala un aviso.
     *
     * @param  Builder<Cuenta>  $consulta
     * @return Builder<Cuenta>
     */
    public function enAviso(Builder $consulta, string $aviso): Builder
    {
        return match ($aviso) {
            'sin-actividad' => $this->sinActividad($this->cuentas->usables($consulta)),
            'ejecutivo-inactivo' => $this->cuentas->conEjecutivoInactivo($consulta),
            'ficha-inactiva' => $consulta->where('cuentas.es_activa', true)->whereIn('cuentas.party_id', Party::query()->select('id')->where('es_activo', false)),
            'sin-rubro' => $this->cuentas->usables($consulta)->whereIn('cuentas.party_id', Party::query()->select('id')->whereNull('rubro_id')),
            default => $consulta,
        };
    }

    /**
     * Cuántas hay en cada aviso, sin los que hoy no existen (D11.1). Solo los que tienen alguna.
     *
     * @return array<string, int>
     */
    public function avisos(): array
    {
        $avisos = [];

        foreach (self::AVISOS as $aviso) {
            if ($aviso === 'sin-actividad' && ! $this->actividad->tiene(DatoActividad::UltimoContacto)) {
                continue;
            }

            $cantidad = $this->enAviso(Cuenta::query(), $aviso)->count();

            if ($cantidad > 0) {
                $avisos[$aviso] = $cantidad;
            }
        }

        return $avisos;
    }

    /**
     * Cuentas usables por ejecutivo, de más a menos (D11.1); un clic filtra la tabla.
     *
     * @return list<array{id: int, nombre: string, activo: bool, cuentas: int}>
     */
    public function cartera(): array
    {
        $porEjecutivo = $this->cuentas->usables(Cuenta::query())
            ->selectRaw('cuentas.ejecutivo_user_id, count(*) AS cuantas')
            ->groupBy('cuentas.ejecutivo_user_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->ejecutivo_user_id => (int) $fila->cuantas]);
        $ejecutivos = User::query()->whereKey($porEjecutivo->keys()->all())->get(['id', 'name', 'es_activo']);

        return $ejecutivos
            ->map(fn (User $u): array => ['id' => $u->id, 'nombre' => $u->name, 'activo' => $u->es_activo, 'cuentas' => $porEjecutivo[$u->id]])
            ->sortBy([['cuentas', 'desc'], ['nombre', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Los últimos cambios de cuentas y sus marcas, de la bitácora: quién, qué, a cuál y cuándo (D11.1).
     *
     * @return list<array{quien: string, accion: string, ficha: string, cuenta_id: ?int, cuando: CarbonImmutable}>
     */
    public function ultimosCambios(int $cuantos = 10): array
    {
        $cambios = AuditoriaCambio::query()
            ->whereIn('auditable_type', [(new Cuenta)->getMorphClass(), (new CuentaMarcaCliente)->getMorphClass()])
            ->with('usuario:id,name')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($cuantos)
            ->get();
        $deMarcas = CuentaMarcaCliente::query()->whereKey($cambios->where('auditable_type', 'cuenta_marca_cliente')->pluck('auditable_id'))->pluck('cuenta_id', 'id');
        $cuentaDe = fn (AuditoriaCambio $c): ?int => $c->auditable_type === 'cuenta'
            ? $c->auditable_id
            : ($deMarcas[$c->auditable_id] ?? $c->valores_anteriores['cuenta_id'] ?? $c->valores_nuevos['cuenta_id'] ?? null);
        $nombres = Cuenta::query()->whereKey($cambios->map($cuentaDe)->filter()->unique()->values()->all())
            ->join('parties', 'parties.id', '=', 'cuentas.party_id')
            ->pluck('parties.razon_social', 'cuentas.id');

        return $cambios->map(function (AuditoriaCambio $c) use ($cuentaDe, $nombres): array {
            $cuentaId = $cuentaDe($c);
            $existe = $cuentaId !== null && isset($nombres[$cuentaId]);

            return [
                'quien' => $c->usuario->name ?? 'Alguien',
                'accion' => $c->auditable_type === 'cuenta' ? $c->evento->etiqueta() : $c->evento->etiqueta().' la marca de cliente de',
                'ficha' => $existe ? (string) $nombres[$cuentaId] : 'una cuenta',
                'cuenta_id' => $existe ? $cuentaId : null,
                'cuando' => $c->fecha,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<Cuenta>  $consulta
     * @return Builder<Cuenta>
     */
    private function sinActividad(Builder $consulta): Builder
    {
        $dias = app(ParametrosService::class)->entero(CuentasServiceProvider::PARAMETRO_DIAS_SIN_ACTIVIDAD);
        $this->actividad->sinActividadDesde($consulta, CarbonImmutable::parse(Calendario::hoy())->subDays($dias)->toDateString());

        return $consulta;
    }
}
