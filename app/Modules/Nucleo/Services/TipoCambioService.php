<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Enums\ResultadoSincronizacion;
use App\Modules\Nucleo\Excepciones\FuenteTipoCambioNoDisponible;
use App\Modules\Nucleo\Excepciones\TipoCambioFaltante;
use App\Modules\Nucleo\Excepciones\TipoCambioInvalido;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\TipoCambio\FuenteTipoCambio;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de cambio diario (M5). Es la única puerta de escritura: la sincronización automática desde la
 * fuente (ADR 0012: SUNAT) y el registro manual de respaldo pasan por aquí.
 */
final class TipoCambioService
{
    public function __construct(
        private readonly CatalogosService $catalogos,
        private readonly ParametrosService $parametros,
        private readonly FuenteTipoCambio $fuente,
    ) {}

    /**
     * Trae el TC vigente de la fuente y lo guarda para la fecha que la fuente indica. Nunca pisa un valor
     * registrado a mano. La consulta va antes y fuera de la transacción.
     *
     * @throws FuenteTipoCambioNoDisponible
     */
    public function sincronizar(): ResultadoSincronizacion
    {
        $lectura = $this->fuente->vigente();

        return $this->guardar($lectura->moneda, $lectura->fecha, $lectura->compra, $lectura->venta, $this->fuente->origen(), respetarManual: true)[1];
    }

    /** Si ya hay algún tipo de cambio registrado para esa fecha (traído o a mano). */
    public function hayDe(CarbonImmutable $fecha): bool
    {
        return TipoCambio::query()->where('fecha', $fecha->toDateString())->exists();
    }

    /**
     * El TC de la moneda para la fecha de negocio. Sin TC ese día, se acepta el último registrado dentro
     * de `tipo_cambio.dias_tolerancia` días (ADR 0012: 4; con 0 el del día es obligatorio). La moneda
     * local vale 1.
     *
     * @throws TipoCambioFaltante
     */
    public function del(string $moneda, CarbonImmutable $fecha): TipoCambioDelDia
    {
        $fecha = $fecha->startOfDay();

        if ($moneda === $this->parametros->monedaLocal()) {
            return new TipoCambioDelDia($moneda, $fecha, '1.0000', '1.0000', OrigenTipoCambio::Manual);
        }

        $tolerancia = max(0, $this->parametros->entero('tipo_cambio.dias_tolerancia'));

        $tc = TipoCambio::query()
            ->where('moneda_id', $this->catalogos->idPorCodigo(Catalogo::Monedas, $moneda))
            ->whereBetween('fecha', [$fecha->subDays($tolerancia)->toDateString(), $fecha->toDateString()])
            ->orderByDesc('fecha')
            ->first() ?? throw TipoCambioFaltante::para($moneda, $fecha->format('d/m/Y'));

        return $this->aDato($moneda, $tc);
    }

    /**
     * Registra el TC del día o corrige el que había (la bitácora guarda el anterior). Es el respaldo
     * manual: reemplaza también lo que trajo la fuente.
     *
     * @throws TipoCambioInvalido
     */
    public function registrar(string $moneda, CarbonImmutable $fecha, string $compra, string $venta, OrigenTipoCambio $origen): TipoCambioDelDia
    {
        return $this->aDato($moneda, $this->guardar($moneda, $fecha, $compra, $venta, $origen, respetarManual: false)[0]->refresh());
    }

    /**
     * @return array{0: TipoCambio, 1: ResultadoSincronizacion}
     *
     * @throws TipoCambioInvalido
     */
    private function guardar(string $moneda, CarbonImmutable $fecha, string $compra, string $venta, OrigenTipoCambio $origen, bool $respetarManual): array
    {
        if ($moneda === $this->parametros->monedaLocal()) {
            throw TipoCambioInvalido::monedaLocal($moneda);
        }

        foreach (['compra' => $compra, 'venta' => $venta] as $campo => $valor) {
            if (preg_match('/^\d{1,6}(\.\d{1,4})?$/', $valor) !== 1 || bccomp($valor, '0', 4) <= 0) {
                throw TipoCambioInvalido::valor($campo);
            }
        }

        $monedaId = $this->catalogos->idPorCodigo(Catalogo::Monedas, $moneda);

        $intento = fn (): array => DB::transaction(function () use ($monedaId, $fecha, $compra, $venta, $origen, $respetarManual): array {
            $tc = TipoCambio::query()
                ->where('moneda_id', $monedaId)
                ->where('fecha', $fecha->toDateString())
                ->lockForUpdate()
                ->first();

            // Se decide con la fila bloqueada: un registro manual que llegue antes no se pisa.
            if ($tc !== null && $respetarManual && $tc->origen === OrigenTipoCambio::Manual) {
                return [$tc, ResultadoSincronizacion::SeRespetaManual];
            }

            $resultado = $tc === null ? ResultadoSincronizacion::Creado : ResultadoSincronizacion::Actualizado;
            $tc ??= new TipoCambio(['moneda_id' => $monedaId, 'fecha' => $fecha->toDateString()]);
            $tc->fill(['compra' => $compra, 'venta' => $venta, 'origen' => $origen]);

            if ($tc->exists && ! $tc->isDirty()) {
                return [$tc, ResultadoSincronizacion::SinCambios];
            }

            $tc->save();

            return [$tc, $resultado];
        });

        try {
            return $intento();
        } catch (UniqueConstraintViolationException) {
            // Otro proceso creó el TC de ese día entre la lectura y el guardado (la fila no existía y no se
            // podía bloquear). Ahora existe: se repite como corrección, con la fila bloqueada.
            return $intento();
        }
    }

    private function aDato(string $moneda, TipoCambio $tc): TipoCambioDelDia
    {
        return new TipoCambioDelDia($moneda, $tc->fecha, $tc->compra, $tc->venta, $tc->origen);
    }
}
