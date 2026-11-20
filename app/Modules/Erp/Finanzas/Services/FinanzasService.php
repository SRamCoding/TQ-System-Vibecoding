<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Services;

use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Finanzas\Models\Comprobante;
use App\Modules\Erp\Finanzas\Models\Fondo;
use App\Modules\Erp\Finanzas\Models\FondoGasto;
use App\Modules\Erp\Finanzas\Models\MovimientoCaja;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Numeracion\ReinicioNumeracion;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t5-finanzas, flujos 4 a 7): el ciclo del dinero. La valorización aprobada se factura y se cobra; la
 * factura del proveedor se registra contra su orden (cruce de tres vías: orden, recepción y factura), Tesorería la
 * programa y la paga, y el pago pasa a «pagado» en las partidas; los fondos por proyecto se rinden con comprobantes.
 * Todo movimiento de dinero queda en caja y banco, conciliable. El resultado real del proyecto sale de aquí.
 */
final readonly class FinanzasService
{
    public const string PERMISO_VER = 'erp.finanzas.ver';

    public const string PERMISO_FACTURAR = 'erp.finanzas.facturar';

    public const string PERMISO_PAGAR = 'erp.finanzas.pagar';

    public const string IGV = '0.18';

    /** La detracción de los contratos de obra y servicios a entidades (4 %), sobre el total. */
    public const string DETRACCION = '0.04';

    public const array CUENTAS = ['Banco Norte · cuenta corriente', 'Banco del Pacífico · cuenta corriente', 'Banco de la Nación · detracciones', 'Caja'];

    private function igv(string $subtotal): string
    {
        return bcadd(bcmul($subtotal, self::IGV, 4), '0.005', 2);
    }

    /** Emite la factura de venta por el neto de una valorización aprobada (la bandeja por facturar). */
    public function facturarValorizacion(int $valorizacionId, CarbonImmutable $emision): Comprobante
    {
        return DB::transaction(function () use ($valorizacionId, $emision): Comprobante {
            $v = DB::table('valorizaciones')->lockForUpdate()->find($valorizacionId);

            if ($v === null || $v->estado !== 'aprobada') {
                throw new RuntimeException('Solo se factura una valorización aprobada y sin facturar.');
            }

            $c = DB::table('contratos')->find($v->contrato_id);
            $comp = $this->emitir($c->proyecto_id, $c->id, (string) $v->neto, $emision, $valorizacionId);
            DB::table('valorizaciones')->where('id', $valorizacionId)->update(['estado' => 'facturada']);

            return $comp;
        });
    }

    /** Una factura de venta directa contra el contrato (entregas sin valorización, suministros). */
    public function facturarContrato(int $contratoId, string $subtotal, CarbonImmutable $emision): Comprobante
    {
        $c = DB::table('contratos')->find($contratoId);

        return DB::transaction(fn (): Comprobante => $this->emitir($c->proyecto_id, $c->id, $subtotal, $emision, null));
    }

    private function emitir(int $proyectoId, int $contratoId, string $subtotal, CarbonImmutable $emision, ?int $valorizacionId): Comprobante
    {
        $c = DB::table('contratos')->find($contratoId);
        $cliente = DB::table('parties')->find($c->cliente_party_id);
        $igv = $this->igv($subtotal);
        $total = bcadd($subtotal, $igv, 2);
        $detraccion = $c->es_publico ? bcadd(bcmul($total, self::DETRACCION, 4), '0.005', 2) : '0.00';
        $numero = app(NumeracionService::class)->siguiente('comprobante', new FormatoNumeracion('F001', ReinicioNumeracion::Nunca, 8), Calendario::ahora());

        return Comprobante::query()->create([
            'clase' => 'venta', 'serie_numero' => $numero, 'ruc' => $cliente->numero_documento, 'razon_social' => $cliente->razon_social,
            'emision' => $emision->toDateString(), 'vencimiento' => $emision->addDays(30)->toDateString(), 'subtotal' => $subtotal, 'igv' => $igv, 'total' => $total,
            'detraccion' => $detraccion, 'proyecto_id' => $proyectoId, 'contrato_id' => $contratoId, 'valorizacion_id' => $valorizacionId, 'origen' => 'emitido',
            'estado' => 'por_cobrar', 'saldo' => $total,
        ]);
    }

    /** El cobro: entra a banco (la detracción a su cuenta del Banco de la Nación). */
    public function cobrar(int $comprobanteId, CarbonImmutable $fecha, string $cuenta = self::CUENTAS[0]): void
    {
        DB::transaction(function () use ($comprobanteId, $fecha, $cuenta): void {
            $c = Comprobante::query()->lockForUpdate()->findOrFail($comprobanteId);

            if ($c->clase !== 'venta' || $c->estado !== 'por_cobrar') {
                throw new RuntimeException('Ese comprobante no está por cobrar.');
            }

            $neto = bcsub((string) $c->saldo, (string) $c->detraccion, 2);
            $this->movimiento($fecha, 'ingreso', $cuenta, $neto, "Cobro {$c->serie_numero} · {$c->razon_social}", $c->id, $c->proyecto_id);

            if (bccomp((string) $c->detraccion, '0', 2) > 0) {
                $this->movimiento($fecha, 'ingreso', self::CUENTAS[2], (string) $c->detraccion, "Detracción {$c->serie_numero}", $c->id, $c->proyecto_id);
            }

            $c->update(['estado' => 'cobrado', 'saldo' => 0]);
        });
    }

    /**
     * La factura del proveedor contra su orden: el cruce de tres vías compara lo facturado con lo recibido a precio de
     * la orden (tolerancia S/ 0.10). Queda por programar el pago.
     */
    public function registrarCompra(int $ordenId, string $serieNumero, ?string $ruc, CarbonImmutable $emision, int $diasCredito, ?string $subtotal = null, string $origen = 'xml'): Comprobante
    {
        return DB::transaction(function () use ($ordenId, $serieNumero, $ruc, $emision, $diasCredito, $subtotal, $origen): Comprobante {
            $o = Orden::query()->with('lineas')->findOrFail($ordenId);
            $recibido = $o->lineas->reduce(fn (string $s, $l): string => bcadd($s, bcadd(bcmul((string) $l->cantidad_recibida, (string) $l->precio_unitario, 4), '0.005', 2), 2), '0');
            $facturado = $subtotal ?? $recibido;

            if (bccomp($facturado, '0', 2) <= 0) {
                throw new RuntimeException('No hay nada recibido que facturar en esa orden.');
            }

            $igv = $this->igv($facturado);
            $total = bcadd($facturado, $igv, 2);

            return Comprobante::query()->create([
                'clase' => 'compra', 'serie_numero' => $serieNumero, 'ruc' => $ruc, 'razon_social' => $o->proveedor_nombre, 'emision' => $emision->toDateString(),
                'vencimiento' => $emision->addDays($diasCredito)->toDateString(), 'subtotal' => $facturado, 'igv' => $igv, 'total' => $total, 'proyecto_id' => $o->proyecto_id,
                'orden_id' => $o->id, 'origen' => $origen, 'sunat' => 'valido', 'cruce' => abs((float) bcsub($facturado, $recibido, 2)) > 0.10 ? 'diferencia' : 'ok',
                'estado' => 'por_programar', 'saldo' => $total,
            ]);
        });
    }

    public function programar(int $comprobanteId, CarbonImmutable $fecha): void
    {
        Comprobante::query()->whereKey($comprobanteId)->whereIn('estado', ['por_programar', 'programado'])->update(['estado' => 'programado', 'pago_programado' => $fecha->toDateString()]);
    }

    /** Paga: sale del banco y pasa a «pagado» en las partidas de su orden (sin IGV). */
    public function pagar(int $comprobanteId, CarbonImmutable $fecha, string $cuenta = self::CUENTAS[0]): void
    {
        DB::transaction(function () use ($comprobanteId, $fecha, $cuenta): void {
            $c = Comprobante::query()->lockForUpdate()->findOrFail($comprobanteId);

            if ($c->clase !== 'compra' || ! in_array($c->estado, ['por_programar', 'programado'], true)) {
                throw new RuntimeException('Ese comprobante no está por pagar.');
            }

            if ($c->cruce === 'diferencia') {
                throw new RuntimeException('El cruce de tres vías tiene diferencia: no se paga hasta resolverla.');
            }

            $this->movimiento($fecha, 'egreso', $cuenta, (string) $c->saldo, "Pago {$c->serie_numero} · {$c->razon_social}", $c->id, $c->proyecto_id);
            $restante = (string) $c->subtotal;
            $imputaciones = app(ImputacionService::class);

            foreach ($c->orden_id === null ? [] : Orden::query()->findOrFail($c->orden_id)->lineas as $l) {
                $imp = $imputaciones->de($l);
                $falta = $imp === null ? '0' : bcsub((string) $imp->monto_ejecutado, (string) $imp->monto_pagado, 2);
                $monto = bccomp($falta, $restante, 2) > 0 ? $restante : $falta;

                if (bccomp($monto, '0', 2) > 0) {
                    $imputaciones->mover($l, MomentoImputacion::Pagado, $monto);
                    $restante = bcsub($restante, $monto, 2);
                }
            }

            $c->update(['estado' => 'pagado', 'saldo' => 0]);
        });
    }

    public function crearFondo(int $proyectoId, string $tipo, string $responsable, string $monto, CarbonImmutable $fecha): Fondo
    {
        return DB::transaction(function () use ($proyectoId, $tipo, $responsable, $monto, $fecha): Fondo {
            $f = Fondo::query()->create(['codigo' => app(NumeracionService::class)->siguiente('fondo', new FormatoNumeracion($tipo === 'caja_chica' ? 'CCH' : 'REN'), Calendario::ahora()),
                'tipo' => $tipo, 'proyecto_id' => $proyectoId, 'responsable' => $responsable, 'monto' => $monto, 'entregado' => $fecha->toDateString()]);
            $this->movimiento($fecha, 'egreso', self::CUENTAS[3], $monto, "Entrega de fondos {$f->codigo} a {$responsable}", null, $proyectoId, $f->id);

            return $f;
        });
    }

    public function registrarGasto(int $fondoId, CarbonImmutable $fecha, string $descripcion, ?string $comprobante, string $monto, int $partidaId, bool $leidoPorIa = false): FondoGasto
    {
        $f = Fondo::query()->findOrFail($fondoId);

        if ($f->estado !== 'abierto') {
            throw new RuntimeException('El fondo ya se rindió.');
        }

        return FondoGasto::query()->create(['fondo_id' => $f->id, 'fecha' => $fecha->toDateString(), 'descripcion' => $descripcion, 'comprobante' => $comprobante,
            'monto' => $monto, 'partida_control_id' => $partidaId, 'leido_por_ia' => $leidoPorIa]);
    }

    /** Aprobado, el gasto de la rendición es costo comprometido, ejecutado y pagado de su partida. */
    public function aprobarGasto(int $gastoId): void
    {
        DB::transaction(function () use ($gastoId): void {
            $g = FondoGasto::query()->lockForUpdate()->findOrFail($gastoId);

            if ($g->estado !== 'por_aprobar') {
                return;
            }

            $imp = app(ImputacionService::class);
            $imp->destinar($g, app(PartidasService::class)->destino($g->partida_control_id), $g->fecha, 'PEN');

            foreach ([MomentoImputacion::Comprometido, MomentoImputacion::Ejecutado, MomentoImputacion::Pagado] as $m) {
                $imp->mover($g, $m, (string) $g->monto);
            }

            $g->update(['estado' => 'aprobado']);
        });
    }

    /** Rinde el fondo: lo no gastado vuelve a caja. */
    public function rendir(int $fondoId, CarbonImmutable $fecha): void
    {
        DB::transaction(function () use ($fondoId, $fecha): void {
            $f = Fondo::query()->lockForUpdate()->findOrFail($fondoId);

            if (FondoGasto::query()->where('fondo_id', $f->id)->where('estado', 'por_aprobar')->exists()) {
                throw new RuntimeException('Faltan aprobar gastos de la rendición.');
            }

            $gastado = (string) (FondoGasto::query()->where('fondo_id', $f->id)->where('estado', 'aprobado')->sum('monto') ?: '0');
            $devuelve = bcsub((string) $f->monto, $gastado, 2);

            if (bccomp($devuelve, '0', 2) > 0) {
                $this->movimiento($fecha, 'ingreso', self::CUENTAS[3], $devuelve, "Devolución del saldo de {$f->codigo}", null, $f->proyecto_id, $f->id);
            }

            $f->update(['estado' => 'rendido']);
        });
    }

    public function conciliar(int $movimientoId, bool $conciliado = true): void
    {
        MovimientoCaja::query()->whereKey($movimientoId)->update(['conciliado' => $conciliado]);
    }

    /**
     * «¿Cuánto se ganó de verdad?»: lo facturado y cobrado contra lo que costó y lo que se pagó.
     *
     * @return array{vendido: ?string, facturado: string, cobrado: string, por_cobrar: string, costo: string, pagado_costo: string, por_pagar: string, margen_real: string, margen_pct: ?string, caja: string}
     */
    public function resultado(int $proyectoId): array
    {
        $ventas = Comprobante::query()->where('proyecto_id', $proyectoId)->where('clase', 'venta')->where('estado', '!=', 'anulado');
        $facturado = bcadd((string) ((clone $ventas)->sum('subtotal') ?: '0'), '0', 2);
        $porCobrar = bcadd((string) ((clone $ventas)->sum('saldo') ?: '0'), '0', 2);
        $d = app(DineroDelProyecto::class)->de($proyectoId);
        $costo = $d['ejecutado'] ?? '0.00';
        $ingresos = (string) (MovimientoCaja::query()->where('proyecto_id', $proyectoId)->where('tipo', 'ingreso')->sum('monto') ?: '0');
        $egresos = (string) (MovimientoCaja::query()->where('proyecto_id', $proyectoId)->where('tipo', 'egreso')->sum('monto') ?: '0');
        $porPagar = (string) (Comprobante::query()->where('proyecto_id', $proyectoId)->where('clase', 'compra')->whereIn('estado', ['por_programar', 'programado'])->sum('saldo') ?: '0');
        $margen = bcsub($facturado, $costo, 2);

        return [
            'vendido' => $d['venta'] ?? null, 'facturado' => $facturado, 'cobrado' => bcadd((string) ((clone $ventas)->where('estado', 'cobrado')->sum('total') ?: '0'), '0', 2),
            'por_cobrar' => $porCobrar, 'costo' => $costo, 'pagado_costo' => $d['pagado'] ?? '0.00', 'por_pagar' => bcadd($porPagar, '0', 2), 'margen_real' => $margen,
            'margen_pct' => bccomp($facturado, '0', 2) > 0 ? bcmul(bcdiv($margen, $facturado, 6), '100', 1) : null, 'caja' => bcsub($ingresos, $egresos, 2),
        ];
    }

    /**
     * El flujo de caja por mes: lo real (movimientos) y lo proyectado (por cobrar y por pagar según su vencimiento o
     * fecha programada).
     *
     * @return list<array{mes: string, ingresos: string, egresos: string, neto: string, proyectado: bool}>
     */
    public function flujo(string $desde, int $meses): array
    {
        $filas = [];
        $hoy = Calendario::ahora()->format('Y-m');

        for ($i = 0; $i < $meses; $i++) {
            $mes = CarbonImmutable::parse($desde.'-01')->addMonths($i);
            $clave = $mes->format('Y-m');
            [$y, $m] = [(int) $mes->format('Y'), (int) $mes->format('n')];
            $real = fn (string $tipo): string => (string) (MovimientoCaja::query()->where('tipo', $tipo)->whereYear('fecha', $y)->whereMonth('fecha', $m)->sum('monto') ?: '0');
            $ing = $real('ingreso');
            $egr = $real('egreso');

            if ($clave >= $hoy) {
                $ing = bcadd($ing, (string) (Comprobante::query()->where('clase', 'venta')->where('estado', 'por_cobrar')->whereYear('vencimiento', $y)->whereMonth('vencimiento', $m)->sum('saldo') ?: '0'), 2);
                $egr = bcadd($egr, (string) (Comprobante::query()->where('clase', 'compra')->whereIn('estado', ['por_programar', 'programado'])
                    ->whereRaw('EXTRACT(YEAR FROM COALESCE(pago_programado, vencimiento)) = ? AND EXTRACT(MONTH FROM COALESCE(pago_programado, vencimiento)) = ?', [$y, $m])->sum('saldo') ?: '0'), 2);
            }

            $filas[] = ['mes' => $clave, 'ingresos' => bcadd($ing, '0', 2), 'egresos' => bcadd($egr, '0', 2), 'neto' => bcsub($ing, $egr, 2), 'proyectado' => $clave >= $hoy];
        }

        return $filas;
    }

    private function movimiento(CarbonImmutable $fecha, string $tipo, string $cuenta, string $monto, string $concepto, ?int $comprobanteId, ?int $proyectoId, ?int $fondoId = null): void
    {
        MovimientoCaja::query()->create(['fecha' => $fecha->toDateString(), 'tipo' => $tipo, 'cuenta' => $cuenta, 'monto' => $monto, 'concepto' => mb_substr($concepto, 0, 250),
            'comprobante_id' => $comprobanteId, 'proyecto_id' => $proyectoId, 'fondo_id' => $fondoId]);
    }
}
