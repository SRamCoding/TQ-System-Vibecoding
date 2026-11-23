<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * C1 sobre la base (plan 2.1, D6 y D7). El saldo se calcula al pedirlo, sin guardarlo y sin bloqueo (es un aviso para
 * quien pide): presupuestado de la última versión aprobada del árbol, y lo imputado a la partida y a todas sus
 * descendientes, en soles con `ROUND(SUM(monto * tipo_cambio), 2)` sobre el total, como
 * `ImputacionService::totalesPorCentroDeCosto`.
 */
final readonly class PartidasEnBase implements PartidasService
{
    public const int LIMITE_MAXIMO = 50;

    public function __construct(private ProyectosService $proyectos) {}

    public function buscar(int $proyectoId, string $texto = '', int $limite = 20): array
    {
        try {
            if (! $this->proyectos->resumen($proyectoId)->aceptaCostos) {
                return [];
            }
        } catch (ProyectoRechazado) {
            return [];
        }

        $partidas = PartidaControl::query()
            ->where('proyecto_id', $proyectoId)
            ->whereHas('arbol.estado', fn ($query) => $query->where('codigo', DefinicionArbolPartidas::APROBADO))
            ->orderBy('codigo')
            ->get()
            ->keyBy('id');
        $buscado = self::normalizar($texto);

        return $partidas
            ->filter(fn (PartidaControl $p): bool => $p->es_hoja && $p->es_activa && ! $p->es_no_presupuestado)
            ->filter(fn (PartidaControl $p): bool => $buscado === '' || $this->linaje($p, $partidas)->contains(
                fn (PartidaControl $nodo): bool => str_contains(self::normalizar("{$nodo->codigo} {$nodo->nombre}"), $buscado),
            ))
            ->take(max(1, min($limite, self::LIMITE_MAXIMO)))
            ->map(fn (PartidaControl $p): ResumenPartida => $this->resumir($p, $partidas))
            ->values()
            ->all();
    }

    public function resumen(int $partidaId): ?ResumenPartida
    {
        $partida = PartidaControl::query()->find($partidaId);

        if ($partida === null) {
            return null;
        }

        $delProyecto = PartidaControl::query()->where('proyecto_id', $partida->proyecto_id)->get()->keyBy('id');

        return $this->resumir($partida, $delProyecto);
    }

    public function saldo(int $partidaId, bool $bloquear = false): SaldoPartida
    {
        if ($bloquear && DB::transactionLevel() === 0) {
            throw new LogicException('El saldo con bloqueo se pide dentro de la transacción que sube el comprometido: fuera de ella, el bloqueo no sirve.');
        }

        $proyectoId = PartidaControl::query()->whereKey($partidaId)->when($bloquear, fn ($query) => $query->lockForUpdate())->value('proyecto_id')
            ?? throw ImputacionRechazada::conMotivo('Esa partida no existe.');
        $arbolAprobado = ArbolPartidas::query()
            ->where('proyecto_id', $proyectoId)
            ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionArbolPartidas::APROBADO))
            ->orderByDesc('numero_version')
            ->value('id');

        $rama = 'WITH RECURSIVE rama AS (
                SELECT id FROM partidas_control WHERE id = ?
                UNION ALL
                SELECT hija.id FROM partidas_control hija JOIN rama ON hija.padre_id = rama.id
            )';

        $presupuestado = $arbolAprobado === null ? '0.00' : (string) DB::selectOne(
            "{$rama} SELECT COALESCE(SUM(monto), 0) AS total FROM partida_presupuestos WHERE arbol_id = ? AND partida_id IN (SELECT id FROM rama)",
            [$partidaId, $arbolAprobado],
        )->total;

        $imputado = DB::selectOne(
            "{$rama} SELECT
                COALESCE(ROUND(SUM(monto_comprometido * tipo_cambio), 2), 0) AS comprometido,
                COALESCE(ROUND(SUM(monto_ejecutado * tipo_cambio), 2), 0) AS ejecutado,
                COALESCE(ROUND(SUM(monto_pagado * tipo_cambio), 2), 0) AS pagado
            FROM imputaciones WHERE partida_control_id IN (SELECT id FROM rama)",
            [$partidaId],
        );

        $presupuestado = bcadd($presupuestado, '0', 2);
        $comprometido = bcadd((string) $imputado->comprometido, '0', 2);

        return new SaldoPartida(
            presupuestado: $presupuestado,
            comprometido: $comprometido,
            ejecutado: bcadd((string) $imputado->ejecutado, '0', 2),
            pagado: bcadd((string) $imputado->pagado, '0', 2),
            disponible: bcsub($presupuestado, $comprometido, 2),
        );
    }

    /**
     * Lo mismo que `saldo()` por cada partida, pero con dos consultas por proyecto en vez de cuatro por partida: cada
     * partida suma su rama (ella y sus descendientes) con el mismo redondeo, y lo presupuestado sale del último árbol
     * aprobado de su proyecto, como en `saldo()`.
     */
    public function saldos(array $partidaIds): array
    {
        $partidaIds = array_values(array_unique(array_map('intval', $partidaIds)));

        if ($partidaIds === []) {
            return [];
        }

        $porProyecto = PartidaControl::query()->whereIn('id', $partidaIds)->pluck('proyecto_id', 'id')
            ->groupBy(fn (mixed $proyectoId): int => (int) $proyectoId, preserveKeys: true);
        $saldos = [];

        foreach ($porProyecto as $proyectoId => $ids) {
            $ids = array_map('intval', $ids->keys()->all());
            $arbolAprobado = ArbolPartidas::query()
                ->where('proyecto_id', $proyectoId)
                ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionArbolPartidas::APROBADO))
                ->orderByDesc('numero_version')
                ->value('id');
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $ramas = "WITH RECURSIVE rama AS (
                    SELECT id AS raiz, id FROM partidas_control WHERE id IN ({$marcas})
                    UNION ALL
                    SELECT rama.raiz, hija.id FROM partidas_control hija JOIN rama ON hija.padre_id = rama.id
                )";

            $presupuestados = $arbolAprobado === null ? [] : collect(DB::select(
                "{$ramas} SELECT rama.raiz, COALESCE(SUM(pp.monto), 0) AS total
                FROM rama JOIN partida_presupuestos pp ON pp.partida_id = rama.id AND pp.arbol_id = ?
                GROUP BY rama.raiz",
                [...$ids, $arbolAprobado],
            ))->pluck('total', 'raiz')->all();

            $imputados = collect(DB::select(
                "{$ramas} SELECT rama.raiz,
                    COALESCE(ROUND(SUM(i.monto_comprometido * i.tipo_cambio), 2), 0) AS comprometido,
                    COALESCE(ROUND(SUM(i.monto_ejecutado * i.tipo_cambio), 2), 0) AS ejecutado,
                    COALESCE(ROUND(SUM(i.monto_pagado * i.tipo_cambio), 2), 0) AS pagado
                FROM rama JOIN imputaciones i ON i.partida_control_id = rama.id
                GROUP BY rama.raiz",
                $ids,
            ))->keyBy('raiz')->all();

            foreach ($ids as $id) {
                $presupuestado = bcadd((string) ($presupuestados[$id] ?? '0'), '0', 2);
                $imputado = $imputados[$id] ?? null;
                $comprometido = bcadd((string) ($imputado->comprometido ?? '0'), '0', 2);

                $saldos[$id] = new SaldoPartida(
                    presupuestado: $presupuestado,
                    comprometido: $comprometido,
                    ejecutado: bcadd((string) ($imputado->ejecutado ?? '0'), '0', 2),
                    pagado: bcadd((string) ($imputado->pagado ?? '0'), '0', 2),
                    disponible: bcsub($presupuestado, $comprometido, 2),
                );
            }
        }

        return $saldos;
    }

    public function destino(int $partidaId, ?int $motivoId = null, ?string $comentario = null, ?int $centroCostoId = null): DestinoImputacion
    {
        return new DestinoPartida($partidaId, $motivoId, $comentario, $centroCostoId, $this->proyectos);
    }

    public function noPresupuestada(int $proyectoId): ?ResumenPartida
    {
        $partida = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_no_presupuestado', true)->first();

        return $partida === null ? null : $this->resumir($partida, new Collection([$partida->id => $partida]));
    }

    /**
     * La partida y sus padres, de la raíz hacia abajo.
     *
     * @param  Collection<int, PartidaControl>  $partidas
     * @return Collection<int, PartidaControl>
     */
    private function linaje(PartidaControl $partida, Collection $partidas): Collection
    {
        $linaje = [$partida];

        while ($partida->padre_id !== null && $partidas->has($partida->padre_id)) {
            $partida = $partidas->get($partida->padre_id);
            array_unshift($linaje, $partida);
        }

        return new Collection($linaje);
    }

    /**
     * @param  Collection<int, PartidaControl>  $partidas
     */
    private function resumir(PartidaControl $partida, Collection $partidas): ResumenPartida
    {
        $padres = $this->linaje($partida, $partidas)->slice(0, -1)->map(fn (PartidaControl $p): string => $p->codigo)->all();

        return new ResumenPartida(
            id: $partida->id,
            proyectoId: $partida->proyecto_id,
            codigo: $partida->codigo,
            nombre: $partida->nombre,
            ruta: implode(' › ', [...$padres, "{$partida->codigo} {$partida->nombre}"]),
            esNoPresupuestada: $partida->es_no_presupuestado,
            responsableUserId: $partida->responsable_user_id,
            esActiva: $partida->es_activa,
        );
    }

    private static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }
}
