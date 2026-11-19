<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Imputacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * M11 · El único camino para imputar una línea de costo (plan 1.9; regla 3: toda línea de costo tiene destino). Cada
 * módulo, al crear una línea con costo, le da su destino; después, sus efectos mueven los tres montos por diferencias
 * (comprometer al aprobar, ejecutar al despachar o dar conformidad, pagar al aplicar el pago; restar al anular). Una
 * línea sin destino no mueve montos. Los montos nunca son negativos y lo ejecutado nunca pasa lo comprometido: lo
 * revisa aquí con la fila bloqueada, y la base lo exige igual.
 */
final readonly class ImputacionService
{
    /**
     * Todas las columnas de destino: al destinar, las que el destino no usa quedan nulas (plan 2.1, D6: la partida de
     * un proyecto, su proyecto, y el motivo y comentario que pide la PC-99). Son texto: el núcleo no sabe de `Erp`.
     */
    private const array COLUMNAS_DESTINO = ['centro_costo_id', 'partida_control_id', 'proyecto_id', 'motivo_id', 'comentario'];

    /** El máximo de `decimal(14,2)`. */
    private const string MAXIMO = '999999999999.99';

    public function __construct(
        private TipoCambioService $tiposCambio,
        private CatalogosService $catalogos,
        private ContextoAuditoria $auditoria,
    ) {}

    /**
     * Da (o cambia) el destino de la línea, en su moneda y con el tipo de cambio de venta de su fecha (D4: el de las
     * compras, como lo usa `TipoCambioDelDia`). La fecha y el tipo de cambio quedan los de la línea: sus montos se
     * valorizan siempre con ellos; la diferencia de cambio al pagar es de la contabilidad, no del costo. Cambiar el
     * destino solo se puede mientras sus montos estén en cero: mover un costo ya comprometido es una reclasificación,
     * que llega con las partidas (etapa 2, flujo 7).
     */
    public function destinar(Model $origen, DestinoImputacion $destino, CarbonImmutable $fecha, string $moneda): Imputacion
    {
        $datos = [
            ...array_fill_keys(self::COLUMNAS_DESTINO, null),
            ...$destino->columnas(),
            'fecha' => $fecha->toDateString(),
            'moneda_id' => $this->catalogos->idPorCodigo(Catalogo::Monedas, $moneda),
            'tipo_cambio' => $this->tiposCambio->del($moneda, $fecha)->venta,
        ];

        return DB::transaction(function () use ($origen, $destino, $datos): Imputacion {
            // Plan 2.1, D6: el destino se revisa y se bloquea aquí, en la misma transacción que lo guarda; así no se
            // desactiva mientras se le imputa.
            $destino->revisarAlDestinar();

            // Una por línea, decidido por la base: si otro la crea a la vez, esta no choca, sigue como cambio.
            $nueva = new Imputacion(['origen_type' => $origen->getMorphClass(), 'origen_id' => $origen->getKey(), ...$datos]);
            $nueva->setAttribute('created_by', $this->auditoria->idActor());

            if (Imputacion::query()->insertOrIgnore($nueva->getAttributes() + ['created_at' => now()]) === 1) {
                return $this->bloqueada($origen) ?? throw new LogicException('La imputación recién creada no está.');
            }

            $actual = $this->bloqueada($origen) ?? throw new LogicException('La imputación existente no está.');

            if ($this->tieneMontos($actual)) {
                throw ImputacionRechazada::conMotivo('Esta línea ya tiene montos: su destino no se cambia aquí.');
            }

            $actual->update($datos);

            return $actual;
        });
    }

    /**
     * Suma (o resta, con una diferencia negativa) a uno de los tres montos de la línea.
     *
     * @param  string  $diferencia  decimal como texto, con hasta dos decimales (`'1500.00'`, `'-600'`)
     */
    public function mover(Model $origen, MomentoImputacion $momento, string $diferencia): Imputacion
    {
        if (preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', $diferencia) !== 1) {
            throw ImputacionRechazada::conMotivo('El monto va en números, con hasta dos decimales.');
        }

        return DB::transaction(function () use ($origen, $momento, $diferencia): Imputacion {
            $imputacion = $this->bloqueada($origen)
                ?? throw ImputacionRechazada::conMotivo('Esta línea de costo todavía no tiene a dónde va: sin destino no se mueven montos.');

            $columna = $momento->columna();
            $nuevo = bcadd((string) $imputacion->getAttribute($columna), $diferencia, 2);

            if (bccomp($nuevo, '0', 2) < 0) {
                throw ImputacionRechazada::conMotivo('El monto quedaría negativo.');
            }

            if (bccomp($nuevo, self::MAXIMO, 2) > 0) {
                throw ImputacionRechazada::conMotivo('El monto pasa el máximo que se puede guardar.');
            }

            $comprometido = $momento === MomentoImputacion::Comprometido ? $nuevo : $imputacion->monto_comprometido;
            $ejecutado = $momento === MomentoImputacion::Ejecutado ? $nuevo : $imputacion->monto_ejecutado;

            if (bccomp($ejecutado, $comprometido, 2) > 0) {
                throw ImputacionRechazada::conMotivo('Lo ejecutado pasaría lo comprometido.');
            }

            $imputacion->update([$columna => $nuevo]);

            return $imputacion;
        });
    }

    /**
     * Marca o desmarca la línea como excedida de su partida (plan 2.2, D4). Solo la llama `ExcesoDePartidaService`, con
     * la fila bloqueada. Al desmarcar limpia también el motivo y el comentario del exceso: no quedan restos (la bitácora
     * de la imputación guarda los anteriores).
     */
    public function marcarExceso(Model $origen, bool $excede): Imputacion
    {
        return DB::transaction(function () use ($origen, $excede): Imputacion {
            $imputacion = $this->bloqueada($origen)
                ?? throw ImputacionRechazada::conMotivo('Esta línea de costo todavía no tiene a dónde va.');

            // Sin exceso no queda motivo de exceso, tampoco uno escrito de antemano (D4: sin restos). Solo se llama para
            // partidas normales: la PC-99 lleva su propio motivo y no pasa por aquí.
            $cambios = $excede ? ['excede_partida' => true] : ['excede_partida' => false, 'motivo_id' => null, 'comentario' => null];
            $imputacion->fill($cambios);

            if ($imputacion->isDirty()) {
                $imputacion->save();
            }

            return $imputacion;
        });
    }

    /**
     * Si alguna de estas líneas está marcada como excedida de su partida (plan 2.2, D5).
     *
     * @param  list<Model>  $origenes
     */
    public function algunaExcedida(array $origenes): bool
    {
        if ($origenes === []) {
            return false;
        }

        return Imputacion::query()
            ->where('excede_partida', true)
            ->where(function ($query) use ($origenes): void {
                foreach ($origenes as $origen) {
                    $query->orWhere(fn ($una) => $una->where('origen_type', $origen->getMorphClass())->where('origen_id', $origen->getKey()));
                }
            })
            ->exists();
    }

    /**
     * Si alguna de estas líneas va a la PC-99 de su proyecto: un gasto no presupuestado (plan 2.6, D2). Cuál partida es
     * la PC-99 lo sabe Presupuestos, por `FuenteDeDestinos` (parámetro: solo lo usa este método).
     *
     * @param  list<Model>  $origenes
     */
    public function algunaNoPresupuestada(array $origenes, FuenteDeDestinos $fuente): bool
    {
        if ($origenes === []) {
            return false;
        }

        return Imputacion::query()
            ->whereNotNull('partida_control_id')
            ->where(function ($query) use ($origenes): void {
                foreach ($origenes as $origen) {
                    $query->orWhere(fn ($una) => $una->where('origen_type', $origen->getMorphClass())->where('origen_id', $origen->getKey()));
                }
            })
            ->distinct()
            ->pluck('partida_control_id')
            ->contains(fn (int $partidaId): bool => $fuente->esNoPresupuestada($partidaId));
    }

    /** La imputación de la línea, si ya tiene destino. */
    public function de(Model $origen): ?Imputacion
    {
        return Imputacion::query()->where('origen_type', $origen->getMorphClass())->where('origen_id', $origen->getKey())->first();
    }

    /** Si alguna línea de costo apunta a ese proyecto (plan 2.1, D3: un proyecto con costos no se anula). */
    public function tieneLineasDelProyecto(int $proyectoId): bool
    {
        return Imputacion::query()->where('proyecto_id', $proyectoId)->exists();
    }

    /** Si alguna línea de costo apunta a esa partida (plan 2.1, D5: una partida con costos no se desactiva). */
    public function tieneLineasDeLaPartida(int $partidaControlId): bool
    {
        return Imputacion::query()->where('partida_control_id', $partidaControlId)->exists();
    }

    /**
     * Lo comprometido, ejecutado y pagado de cada centro de costo, en soles (cada línea con su tipo de cambio,
     * redondeado al céntimo por la base: nunca pasa por float). Con fechas, solo las líneas de ese rango. Solo el gasto
     * directo: una línea con partida es costo del proyecto, aunque lleve un centro de referencia (la PC-99, plan 2.1,
     * D6); si se contara aquí, saldría dos veces.
     *
     * @return list<array{centro: CentroCosto, comprometido: string, ejecutado: string, pagado: string}>
     */
    public function totalesPorCentroDeCosto(?CarbonImmutable $desde = null, ?CarbonImmutable $hasta = null): array
    {
        $filas = Imputacion::query()
            ->whereNotNull('centro_costo_id')
            ->whereNull('partida_control_id')
            ->when($desde !== null, fn ($query) => $query->where('fecha', '>=', $desde?->toDateString()))
            ->when($hasta !== null, fn ($query) => $query->where('fecha', '<=', $hasta?->toDateString()))
            ->groupBy('centro_costo_id')
            ->selectRaw('centro_costo_id, ROUND(SUM(monto_comprometido * tipo_cambio), 2) AS comprometido, ROUND(SUM(monto_ejecutado * tipo_cambio), 2) AS ejecutado, ROUND(SUM(monto_pagado * tipo_cambio), 2) AS pagado')
            ->toBase()
            ->get()
            ->keyBy('centro_costo_id');

        return CentroCosto::query()->orderBy('codigo')->get()
            ->filter(fn (CentroCosto $centro): bool => $filas->has($centro->id))
            ->map(fn (CentroCosto $centro): array => [
                'centro' => $centro,
                'comprometido' => (string) $filas[$centro->id]->comprometido,
                'ejecutado' => (string) $filas[$centro->id]->ejecutado,
                'pagado' => (string) $filas[$centro->id]->pagado,
            ])
            ->values()
            ->all();
    }

    private function bloqueada(Model $origen): ?Imputacion
    {
        return Imputacion::query()
            ->where('origen_type', $origen->getMorphClass())
            ->where('origen_id', $origen->getKey())
            ->lockForUpdate()
            ->first();
    }

    private function tieneMontos(Imputacion $imputacion): bool
    {
        return bccomp($imputacion->monto_comprometido, '0', 2) !== 0
            || bccomp($imputacion->monto_ejecutado, '0', 2) !== 0
            || bccomp($imputacion->monto_pagado, '0', 2) !== 0;
    }
}
