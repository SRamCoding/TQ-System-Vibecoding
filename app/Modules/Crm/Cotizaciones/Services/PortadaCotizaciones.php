<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que lee la portada de Cotizaciones (D19; `interfaz.md`, portada y «Documentos con versiones: árbol de carpetas»):
 * pestañas por estado, el aviso «Por vencer» con «Ver cuáles», las carpetas Cuenta › Oportunidad › Cotización › Versión
 * (la versión más reciente primero; la carpeta de la oportunidad trae también sus presupuestos, por C8) y los últimos
 * cambios. Todo filtra en la base; las carpetas se arman con lo que dejan los filtros de arriba.
 */
final readonly class PortadaCotizaciones
{
    /** Pestañas, en su orden. «En curso»: borrador o aprobada sin emitir; «Cerradas»: rechazadas, reemplazadas y anuladas. */
    public const array PESTANAS = ['todas', 'en_curso', 'por_aprobar', 'emitidas', 'aceptadas', 'vencidas', 'cerradas'];

    public const array NOMBRES = ['todas' => 'Todas', 'en_curso' => 'En curso', 'por_aprobar' => 'Por aprobar', 'emitidas' => 'Emitidas', 'aceptadas' => 'Aceptadas', 'vencidas' => 'Vencidas', 'cerradas' => 'Cerradas'];

    /** Avisos, en su orden; cada uno es también un valor del filtro «Revisar» de la tabla. */
    public const array AVISOS = ['por-vencer'];

    /** Tope de filas que arman las carpetas: más allá, se pide filtrar. */
    public const int TOPE_CARPETAS = 400;

    /**
     * @param  Builder<Cotizacion>  $consulta
     * @return Builder<Cotizacion>
     */
    public function enPestana(Builder $consulta, string $pestana): Builder
    {
        return match ($pestana) {
            'en_curso' => $this->enEstados($consulta, [DefinicionCotizacion::BORRADOR, DefinicionCotizacion::APROBADA]),
            'por_aprobar' => $this->enEstados($consulta, [DefinicionCotizacion::POR_APROBAR]),
            'emitidas' => $this->enEstados($consulta, [DefinicionCotizacion::EMITIDA]),
            'aceptadas' => $this->enEstados($consulta, [DefinicionCotizacion::ACEPTADA]),
            'vencidas' => $this->enEstados($consulta, [DefinicionCotizacion::VENCIDA]),
            'cerradas' => $this->enEstados($consulta, [DefinicionCotizacion::RECHAZADA, DefinicionCotizacion::REEMPLAZADA, DefinicionCotizacion::ANULADA]),
            default => $consulta,
        };
    }

    /**
     * «Por vencer»: emitidas cuya validez termina dentro de los días del parámetro (hoy incluido). Avisa para llamar al
     * cliente antes de que venza (D10).
     *
     * @param  Builder<Cotizacion>  $consulta
     * @return Builder<Cotizacion>
     */
    public function enAviso(Builder $consulta, string $aviso): Builder
    {
        if ($aviso !== 'por-vencer') {
            return $consulta;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $dias = app(ParametrosService::class)->entero(CotizacionesServiceProvider::PARAMETRO_DIAS_POR_VENCER);

        return $this->enEstados($consulta, [DefinicionCotizacion::EMITIDA])
            ->whereBetween('cotizaciones.fecha_vencimiento', [$hoy->toDateString(), $hoy->addDays($dias)->toDateString()]);
    }

    /** @return array<string, int> */
    public function avisos(): array
    {
        $avisos = [];

        foreach (self::AVISOS as $aviso) {
            $cantidad = $this->enAviso(Cotizacion::query(), $aviso)->count();

            if ($cantidad > 0) {
                $avisos[$aviso] = $cantidad;
            }
        }

        return $avisos;
    }

    /**
     * Las carpetas de lo que dejan los filtros: por cuenta y oportunidad (las más recientes arriba), cada número con sus
     * versiones de la más nueva a la más antigua, y en cada oportunidad sus presupuestos (C8). Con más de
     * `TOPE_CARPETAS` filas, solo las primeras y `recortadas`.
     *
     * @param  Builder<Cotizacion>  $consulta
     * @return array{cuentas: list<array{id: int, etiqueta: string, oportunidades: list<array{id: int, etiqueta: string, numeros: list<array{numero: string, versiones: list<array{id: int, version: int, estado: string, estadoNombre: string, aceptada: bool}>}>, presupuestos: list<array{id: int, etiqueta: string, estado: string}>}>}>, recortadas: bool}
     */
    public function carpetas(Builder $consulta): array
    {
        $filas = $consulta->clone()->reorder()->with('estado')
            ->orderByDesc('cotizaciones.numero')->orderByDesc('cotizaciones.numero_version')
            ->limit(self::TOPE_CARPETAS + 1)
            ->get(['cotizaciones.id', 'cotizaciones.numero', 'cotizaciones.numero_version', 'cotizaciones.oportunidad_id', 'cotizaciones.cuenta_id', 'cotizaciones.estado_id', 'cotizaciones.es_aceptada']);
        $recortadas = $filas->count() > self::TOPE_CARPETAS;
        $filas = $filas->take(self::TOPE_CARPETAS);

        $cuentas = app(CuentasService::class)->resumenesDe($filas->pluck('cuenta_id')->unique()->values()->all());
        $oportunidades = app(OportunidadesService::class)->resumenesDe($filas->pluck('oportunidad_id')->unique()->values()->all());
        $presupuestos = app(PresupuestosService::class);
        $arbol = [];

        foreach ($filas as $c) {
            $arbol[$c->cuenta_id] ??= ['id' => $c->cuenta_id, 'etiqueta' => $cuentas[$c->cuenta_id]->etiqueta ?? 'Cuenta', 'oportunidades' => []];
            $arbol[$c->cuenta_id]['oportunidades'][$c->oportunidad_id] ??= [
                'id' => $c->oportunidad_id,
                'etiqueta' => isset($oportunidades[$c->oportunidad_id]) ? $oportunidades[$c->oportunidad_id]->etiqueta() : 'Oportunidad',
                'numeros' => [],
                'presupuestos' => [],
            ];
            $arbol[$c->cuenta_id]['oportunidades'][$c->oportunidad_id]['numeros'][$c->numero][] = [
                'id' => $c->id, 'version' => $c->numero_version, 'estado' => $c->estado->codigo, 'estadoNombre' => $c->estado->nombre, 'aceptada' => $c->es_aceptada,
            ];
        }

        $cuentasArbol = [];

        foreach ($arbol as $cuenta) {
            $ops = [];

            foreach ($cuenta['oportunidades'] as $op) {
                $numeros = [];

                foreach ($op['numeros'] as $numero => $versiones) {
                    $numeros[] = ['numero' => (string) $numero, 'versiones' => $versiones];
                }

                $ops[] = [...$op, 'numeros' => $numeros, 'presupuestos' => array_map(fn ($p): array => [
                    'id' => $p->id, 'etiqueta' => "{$p->numero} v{$p->numeroVersion} · {$p->nombre}", 'estado' => $p->estadoNombre,
                ], $presupuestos->deOportunidad($op['id']))];
            }

            $cuentasArbol[] = [...$cuenta, 'oportunidades' => $ops];
        }

        return ['cuentas' => $cuentasArbol, 'recortadas' => $recortadas];
    }

    /**
     * Los últimos cambios de cotizaciones (encabezado y estado), de la bitácora.
     *
     * @return list<array{quien: string, accion: string, ficha: string, cotizacion_id: ?int, cuando: CarbonImmutable}>
     */
    public function ultimosCambios(int $cuantos = 10): array
    {
        $cambios = AuditoriaCambio::query()
            ->where('auditable_type', (new Cotizacion)->getMorphClass())
            ->with('usuario:id,name')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($cuantos)
            ->get();
        $nombres = Cotizacion::query()->whereKey($cambios->pluck('auditable_id')->unique()->values()->all())
            ->get(['id', 'numero', 'numero_version'])
            ->mapWithKeys(fn (Cotizacion $c): array => [$c->id => "{$c->numero} v{$c->numero_version}"]);

        return $cambios->map(fn (AuditoriaCambio $c): array => [
            'quien' => $c->usuario->name ?? 'Alguien',
            'accion' => $c->evento->etiqueta(),
            'ficha' => (string) ($nombres[$c->auditable_id] ?? 'una cotización'),
            'cotizacion_id' => isset($nombres[$c->auditable_id]) ? $c->auditable_id : null,
            'cuando' => $c->fecha,
        ])->values()->all();
    }

    /**
     * @param  Builder<Cotizacion>  $consulta
     * @param  list<string>  $codigos
     * @return Builder<Cotizacion>
     */
    private function enEstados(Builder $consulta, array $codigos): Builder
    {
        return $consulta->whereHas('estado', fn (Builder $q): Builder => $q->whereIn('codigo', $codigos));
    }
}
