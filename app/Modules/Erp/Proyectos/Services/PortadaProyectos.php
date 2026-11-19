<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\FiltroSalud;
use App\Modules\Erp\Proyectos\Resumen\RegistroFiltrosSalud;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que lee la portada de la lista de proyectos (plan 2.10, D1), con el patrón de Cuentas: pestañas por estado, el
 * aviso de lo que hay que revisar con «Ver cuáles» y los últimos cambios. Todo filtra en la base. Solo datos del propio
 * proyecto: lo del presupuesto y el árbol se ve en la ficha, por sus pestañas.
 */
final readonly class PortadaProyectos
{
    /** Pestañas, en su orden. «Todos» son de verdad todos, también los cerrados y anulados. */
    public const array PESTANAS = ['todos', 'mios', 'en-preparacion', 'en-ejecucion', 'cerrados', 'anulados'];

    /** Avisos, en su orden; cada uno es también un valor del filtro «Revisar» de la tabla. */
    public const array AVISOS = ['jefe-inactivo'];

    /** El estado de cada pestaña que filtra por estado. */
    private const array ESTADO_DE_PESTANA = [
        'en-preparacion' => DefinicionProyecto::EN_PREPARACION,
        'en-ejecucion' => DefinicionProyecto::EN_EJECUCION,
        'cerrados' => DefinicionProyecto::CERRADO,
        'anulados' => DefinicionProyecto::ANULADO,
    ];

    /**
     * Restringe la consulta a una pestaña. «Míos»: los que la persona lleva como jefe de proyecto.
     *
     * @param  Builder<Proyecto>  $consulta
     * @return Builder<Proyecto>
     */
    public function enPestana(Builder $consulta, string $pestana, ?User $usuario): Builder
    {
        if ($pestana === 'mios') {
            return $usuario === null ? $consulta->whereRaw('false') : $consulta->where('proyectos.jefe_proyecto_user_id', $usuario->id);
        }

        $estado = self::ESTADO_DE_PESTANA[$pestana] ?? null;

        return $estado === null ? $consulta : $this->enEstados($consulta, [$estado]);
    }

    /**
     * Restringe la consulta a lo que señala un aviso: los abiertos (en preparación o en ejecución) cuyo jefe ya no está
     * activo, para cambiarlo.
     *
     * @param  Builder<Proyecto>  $consulta
     * @return Builder<Proyecto>
     */
    public function enAviso(Builder $consulta, string $aviso): Builder
    {
        return match ($aviso) {
            'jefe-inactivo' => $this->enEstados($consulta, DefinicionProyecto::ACEPTAN_COSTOS)
                ->whereIn('proyectos.jefe_proyecto_user_id', User::query()->select('id')->where('es_activo', false)),
            default => $consulta,
        };
    }

    /**
     * Cuántos hay en cada aviso; solo los que tienen alguno.
     *
     * @return array<string, int>
     */
    public function avisos(): array
    {
        $avisos = [];

        foreach (self::AVISOS as $aviso) {
            $cantidad = $this->enAviso(Proyecto::query(), $aviso)->count();

            if ($cantidad > 0) {
                $avisos[$aviso] = $cantidad;
            }
        }

        return $avisos;
    }

    /**
     * La lista de la portada con tarjetas (plan 2.61, D4): todo se filtra en la base. `$salvo` deja fuera un grupo, para
     * contar sus opciones con los demás filtros puestos (la cantidad junto a cada opción). `$saludes` trae los proyectos
     * de cada opción de salud ya calculados (`saludes()`): una opción de costo recorre los proyectos abiertos, así que se
     * calcula una vez por vista y no en cada conteo.
     *
     * @param  ?array<string, list<int>>  $saludes
     * @return Builder<Proyecto>
     */
    public function filtrar(FiltrosPortada $filtros, ?User $usuario, ?string $salvo = null, ?array $saludes = null): Builder
    {
        $consulta = Proyecto::query();
        $texto = trim($filtros->texto);

        if ($texto !== '') {
            // El cliente se busca por su ficha del núcleo (`parties`), como en el buscador de la 2.10.
            $consulta->where(fn (Builder $q) => $q->whereRaw('unaccent(proyectos.nombre) ilike unaccent(?)', ["%{$texto}%"])
                ->orWhere('proyectos.codigo', 'ilike', "%{$texto}%")
                ->orWhereIn('proyectos.party_id', Party::query()->select('id')->whereRaw('unaccent(razon_social) ilike unaccent(?)', ["%{$texto}%"])));
        }

        if ($salvo !== 'estados' && $filtros->estados !== []) {
            $this->enEstados($consulta, $filtros->estados);
        }

        if ($salvo !== 'jefes' && $filtros->jefes !== []) {
            $consulta->whereIn('proyectos.jefe_proyecto_user_id', $filtros->jefes);
        }

        if ($salvo !== 'mios' && $filtros->mios) {
            $consulta->where('proyectos.jefe_proyecto_user_id', $usuario->id ?? 0);
        }

        if ($salvo !== 'salud' && $filtros->salud !== []) {
            $saludes ??= $this->saludes($usuario);

            foreach ($filtros->salud as $clave) {
                if (isset($saludes[$clave])) {
                    $consulta->whereIn('proyectos.id', $saludes[$clave]);
                }
            }
        }

        return $consulta;
    }

    /**
     * Los proyectos de cada opción de salud que la persona puede usar.
     *
     * @return array<string, list<int>>
     */
    public function saludes(?User $usuario): array
    {
        return array_map(fn (FiltroSalud $f): array => $f->proyectos(), app(RegistroFiltrosSalud::class)->para($usuario));
    }

    /**
     * @param  Builder<Proyecto>  $consulta
     * @return Builder<Proyecto>
     */
    public function ordenar(Builder $consulta, string $orden): Builder
    {
        return match ($orden) {
            'fin' => $consulta->orderBy('proyectos.fecha_fin_prevista')->orderBy('proyectos.id'),
            'nombre' => $consulta->orderBy('proyectos.nombre')->orderBy('proyectos.id'),
            default => $consulta->orderByDesc('proyectos.id'),
        };
    }

    /**
     * Las opciones de cada grupo con cuántos proyectos tendrían si se marcaran, con los demás filtros puestos.
     *
     * @param  ?array<string, list<int>>  $saludes
     * @return array{estados: array<string, array{etiqueta: string, cantidad: int}>, jefes: array<int, array{etiqueta: string, cantidad: int}>, salud: array<string, array{etiqueta: string, cantidad: int, tono: string}>}
     */
    public function opciones(FiltrosPortada $filtros, ?User $usuario, ?array $saludes = null): array
    {
        $saludes ??= $this->saludes($usuario);
        $estados = [];
        $porEstado = $this->filtrar($filtros, $usuario, 'estados', $saludes)->join('estados', 'estados.id', '=', 'proyectos.estado_id')
            ->groupBy('estados.codigo')->selectRaw('estados.codigo, count(*) as n')->pluck('n', 'codigo');

        foreach ((new DefinicionProyecto)->estados() as $estado) {
            $estados[$estado->codigo] = ['etiqueta' => $estado->nombre, 'cantidad' => (int) ($porEstado[$estado->codigo] ?? 0)];
        }

        $porJefe = $this->filtrar($filtros, $usuario, 'jefes', $saludes)->groupBy('proyectos.jefe_proyecto_user_id')
            ->selectRaw('proyectos.jefe_proyecto_user_id as jefe, count(*) as n')->pluck('n', 'jefe');
        $todosLosJefes = Proyecto::query()->distinct()->pluck('jefe_proyecto_user_id')->all();
        $jefes = [];

        foreach (User::query()->whereKey($todosLosJefes)->orderBy('name')->get(['id', 'name']) as $jefe) {
            $jefes[$jefe->id] = ['etiqueta' => $jefe->name, 'cantidad' => (int) ($porJefe[$jefe->id] ?? 0)];
        }

        $base = $this->filtrar($filtros, $usuario, 'salud')->pluck('proyectos.id')->all();
        $salud = [];

        foreach (app(RegistroFiltrosSalud::class)->para($usuario) as $clave => $filtro) {
            $salud[$clave] = ['etiqueta' => $filtro->etiqueta(), 'cantidad' => count(array_intersect($base, $saludes[$clave] ?? [])), 'tono' => $filtro->tono()];
        }

        return ['estados' => $estados, 'jefes' => $jefes, 'salud' => $salud];
    }

    /**
     * Los últimos cambios de proyectos, de la bitácora: quién, qué, a cuál y cuándo.
     *
     * @return list<array{quien: string, accion: string, ficha: string, proyecto_id: ?int, cuando: CarbonImmutable}>
     */
    public function ultimosCambios(int $cuantos = 10): array
    {
        $cambios = AuditoriaCambio::query()
            ->where('auditable_type', (new Proyecto)->getMorphClass())
            ->with('usuario:id,name')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($cuantos)
            ->get();
        $nombres = Proyecto::query()->whereKey($cambios->pluck('auditable_id')->unique()->values()->all())
            ->get(['id', 'codigo', 'nombre'])
            ->mapWithKeys(fn (Proyecto $p): array => [$p->id => "{$p->codigo} · {$p->nombre}"]);

        return $cambios->map(fn (AuditoriaCambio $c): array => [
            'quien' => $c->usuario->name ?? 'Alguien',
            'accion' => $c->evento->etiqueta(),
            'ficha' => (string) ($nombres[$c->auditable_id] ?? 'un proyecto'),
            'proyecto_id' => isset($nombres[$c->auditable_id]) ? $c->auditable_id : null,
            'cuando' => $c->fecha,
        ])->values()->all();
    }

    /**
     * La actividad reciente de un proyecto para su Resumen (plan 2.61, D5), de la bitácora que ya existe.
     *
     * @return list<array{quien: string, accion: string, cuando: CarbonImmutable}>
     */
    public function actividadDe(int $proyectoId, int $cuantos = 8): array
    {
        return AuditoriaCambio::query()
            ->where('auditable_type', (new Proyecto)->getMorphClass())->where('auditable_id', $proyectoId)
            ->with('usuario:id,name')->orderByDesc('fecha')->orderByDesc('id')->limit($cuantos)->get()
            ->map(fn (AuditoriaCambio $c): array => ['quien' => $c->usuario->name ?? 'Alguien', 'accion' => $c->evento->etiqueta(), 'cuando' => $c->fecha])
            ->values()->all();
    }

    /**
     * @param  Builder<Proyecto>  $consulta
     * @param  list<string>  $codigos
     * @return Builder<Proyecto>
     */
    private function enEstados(Builder $consulta, array $codigos): Builder
    {
        return $consulta->whereHas('estado', fn (Builder $q): Builder => $q->whereIn('codigo', $codigos));
    }
}
