<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Services;

use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Erp\Compras\Models\Almacen;
use App\Modules\Erp\Compras\Models\CotizacionProveedor;
use App\Modules\Erp\Compras\Models\CuadroComparativo;
use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Compras\Models\OrdenLinea;
use App\Modules\Erp\Compras\Models\Recepcion;
use App\Modules\Erp\Compras\Models\Requerimiento;
use App\Modules\Erp\Compras\Models\RequerimientoLinea;
use App\Modules\Erp\Compras\Models\TipoCompra;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\Imputacion\CentroDeCosto;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t3-compras): el flujo 2, de la necesidad al costo. El requerimiento declara tipo de compra e
 * imputación por línea; al aprobarse, lo que hay en almacén se reserva (y se atenderá con despacho) y lo demás va a
 * Compras. El cuadro comparativo deja escrita la decisión y su motivo; la orden compromete el costo en cada partida al
 * aprobarse (el exceso exige motivo); la recepción en obra (o la conformidad del servicio) lo ejecuta.
 */
final readonly class ComprasService
{
    public const string PERMISO_VER = 'erp.compras.ver';

    public const string PERMISO_REQUERIR = 'erp.compras.requerir';

    public const string PERMISO_APROBAR = 'erp.compras.aprobar';

    public const string PERMISO_COMPRAR = 'erp.compras.comprar';

    public const array ESTADOS_REQ = ['borrador' => 'Borrador', 'por_aprobar' => 'Por aprobar', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado', 'atendido' => 'Atendido'];

    public const array ESTADOS_ORDEN = ['borrador' => 'Borrador', 'aprobada' => 'Aprobada', 'recibida_parcial' => 'Recibida parcial', 'recibida' => 'Recibida', 'anulada' => 'Anulada'];

    /**
     * @param  list<array{tipo: string, producto_id?: ?int, descripcion: string, unidad_medida_id: int, cantidad: string, precio_estimado?: ?string, partida_control_id?: ?int, motivo_id?: ?int}>  $lineas
     */
    public function crearRequerimiento(int $proyectoId, string $descripcion, ?CarbonImmutable $necesidad, array $lineas, User $quien): Requerimiento
    {
        if ($lineas === []) {
            throw new RuntimeException('El requerimiento lleva al menos una línea.');
        }

        return DB::transaction(function () use ($proyectoId, $descripcion, $necesidad, $lineas, $quien): Requerimiento {
            $req = Requerimiento::query()->create([
                'codigo' => $this->numero('requerimiento', 'REQ'), 'proyecto_id' => $proyectoId, 'solicitante_user_id' => $quien->id,
                'descripcion' => trim($descripcion), 'fecha_necesidad' => $necesidad?->toDateString(), 'estado' => 'borrador',
            ]);

            foreach (array_values($lineas) as $i => $l) {
                $precio = $l['precio_estimado'] ?? null;

                if ($precio === null && ($l['producto_id'] ?? null) !== null) {
                    $vigente = app(CatalogoService::class)->precioVigente((int) $l['producto_id']);
                    $precio = $vigente?->costo ?? $vigente?->precio;
                }

                $req->lineas()->create([
                    'tipo_compra_id' => $this->tipo($l['tipo'])->id, 'producto_id' => $l['producto_id'] ?? null, 'descripcion' => trim($l['descripcion']),
                    'unidad_medida_id' => $l['unidad_medida_id'], 'cantidad' => $l['cantidad'], 'precio_estimado' => $precio ?? '0',
                    'partida_control_id' => $l['partida_control_id'] ?? null, 'motivo_id' => $l['motivo_id'] ?? null, 'orden' => $i + 1,
                ]);
            }

            return $req;
        });
    }

    /** Lo que hay disponible del producto en el almacén central (stock menos reservado). */
    public function stockDisponible(int $productoId): string
    {
        $s = DB::table('stock')->join('almacenes', 'almacenes.id', '=', 'stock.almacen_id')->where('almacenes.tipo', 'central')->where('producto_id', $productoId)->first(['cantidad', 'reservado']);

        return $s === null ? '0.000' : bcsub((string) $s->cantidad, (string) $s->reservado, 3);
    }

    /**
     * Cómo queda cada partida con el requerimiento (v4: «PC-04 quedaría al 91 % con la grúa»).
     *
     * @return array<int, array{codigo: string, nombre: string, presupuestado: string, comprometido: string, este: string, porcentaje: ?string, excede: bool}>
     */
    public function vistaPrevia(Requerimiento $req): array
    {
        $porPartida = [];

        foreach ($req->lineas as $l) {
            if ($l->partida_control_id !== null) {
                $porPartida[$l->partida_control_id] = bcadd($porPartida[$l->partida_control_id] ?? '0', bcmul((string) $l->cantidad, (string) $l->precio_estimado, 2), 2);
            }
        }

        $resultado = [];

        foreach ($porPartida as $partidaId => $monto) {
            $s = app(PartidasService::class)->saldo($partidaId);
            $p = DB::table('partidas_control')->where('id', $partidaId)->first(['codigo', 'nombre', 'es_no_presupuestado']);
            $total = bcadd($s->comprometido, $monto, 2);
            $resultado[$partidaId] = [
                'codigo' => $p->codigo, 'nombre' => $p->nombre, 'presupuestado' => $s->presupuestado, 'comprometido' => $s->comprometido, 'este' => $monto,
                'porcentaje' => bccomp($s->presupuestado, '0', 2) > 0 ? bcmul(bcdiv($total, $s->presupuestado, 6), '100', 0) : null,
                'excede' => ! $p->es_no_presupuestado && bccomp($monto, $s->disponible, 2) > 0,
            ];
        }

        return $resultado;
    }

    public function enviar(int $reqId): Requerimiento
    {
        $req = $this->req($reqId, ['borrador', 'rechazado']);
        $sin = $req->lineas->filter(fn (RequerimientoLinea $l): bool => $l->partida_control_id === null)->pluck('orden')->all();

        if ($sin !== []) {
            throw new RuntimeException('No se puede enviar: la línea '.implode(', ', $sin).' no tiene imputación.');
        }

        $req->update(['estado' => 'por_aprobar']);

        return $req;
    }

    /** Aprueba: el exceso exige motivo; lo que hay en almacén se reserva (comprometido a su costo) y lo demás va a Compras. */
    public function aprobar(int $reqId, ?string $motivoExceso, User $quien): Requerimiento
    {
        return DB::transaction(function () use ($reqId, $motivoExceso, $quien): Requerimiento {
            $req = $this->req($reqId, ['por_aprobar']);
            $excesos = array_filter($this->vistaPrevia($req), fn (array $p): bool => $p['excede']);

            if ($excesos !== [] && trim((string) $motivoExceso) === '') {
                throw new RuntimeException('Excede '.implode(', ', array_column($excesos, 'codigo')).': registra el motivo; nadie autoriza un sobrecosto sin saberlo.');
            }

            foreach ($req->lineas as $l) {
                if ($l->producto_id !== null && $l->tipo->es_bien && bccomp($this->stockDisponible($l->producto_id), (string) $l->cantidad, 3) >= 0) {
                    $central = Almacen::query()->where('tipo', 'central')->firstOrFail();
                    $stock = DB::table('stock')->where('almacen_id', $central->id)->where('producto_id', $l->producto_id)->lockForUpdate()->first();
                    DB::table('stock')->where('id', $stock->id)->update(['reservado' => bcadd((string) $stock->reservado, (string) $l->cantidad, 3)]);
                    $l->update(['atencion' => 'despacho', 'estado' => 'reservada', 'precio_estimado' => $stock->costo_promedio]);
                    $this->movimiento($central->id, $l->producto_id, 'reserva', (string) $l->cantidad, (string) $stock->costo_promedio, $req->codigo, $req->proyecto_id);
                    $this->comprometer($l, bcadd(bcmul((string) $l->cantidad, (string) $stock->costo_promedio, 4), '0.005', 2));
                } else {
                    $l->update(['atencion' => 'compra', 'estado' => 'pendiente']);
                }
            }

            $req->update(['estado' => 'aprobado', 'motivo_exceso' => $excesos === [] ? null : $motivoExceso, 'aprobado_por' => $quien->id, 'aprobado_at' => Calendario::ahora()]);

            return $req->refresh();
        });
    }

    public function rechazar(int $reqId, string $comentario): void
    {
        if (trim($comentario) === '') {
            throw new RuntimeException('Rechazar exige comentario.');
        }

        $this->req($reqId, ['por_aprobar'])->update(['estado' => 'rechazado', 'comentario_rechazo' => trim($comentario)]);
    }

    /** Despacha a obra lo reservado: sale del almacén y el costo pasa a ejecutado. */
    public function despachar(int $lineaId, CarbonImmutable $fecha): void
    {
        DB::transaction(function () use ($lineaId, $fecha): void {
            $l = RequerimientoLinea::query()->with('requerimiento')->lockForUpdate()->findOrFail($lineaId);

            if ($l->estado !== 'reservada') {
                throw new RuntimeException('Esa línea no está reservada en almacén.');
            }

            $central = Almacen::query()->where('tipo', 'central')->firstOrFail();
            $stock = DB::table('stock')->where('almacen_id', $central->id)->where('producto_id', $l->producto_id)->lockForUpdate()->first();
            DB::table('stock')->where('id', $stock->id)->update(['cantidad' => bcsub((string) $stock->cantidad, (string) $l->cantidad, 3), 'reservado' => bcsub((string) $stock->reservado, (string) $l->cantidad, 3)]);
            $this->movimiento($central->id, (int) $l->producto_id, 'salida', (string) $l->cantidad, (string) $l->precio_estimado, 'Despacho '.$l->requerimiento->codigo, $l->requerimiento->proyecto_id, $fecha);
            app(ImputacionService::class)->mover($l, MomentoImputacion::Ejecutado, (string) app(ImputacionService::class)->de($l)?->monto_comprometido);
            $l->update(['estado' => 'despachada']);
            $this->cerrarSiAtendido($l->requerimiento);
        });
    }

    public function crearCuadro(int $reqId): CuadroComparativo
    {
        return DB::transaction(function () use ($reqId): CuadroComparativo {
            $req = $this->req($reqId, ['aprobado']);
            $lineas = $req->lineas->filter(fn (RequerimientoLinea $l): bool => $l->atencion === 'compra' && $l->estado === 'pendiente');

            if ($lineas->isEmpty()) {
                throw new RuntimeException('No quedan líneas por comprar.');
            }

            $cuadro = CuadroComparativo::query()->create(['codigo' => $this->numero('cuadro_comparativo', 'CC'), 'requerimiento_id' => $req->id, 'estado' => 'abierto']);
            $lineas->each->update(['estado' => 'en_cuadro']);

            return $cuadro;
        });
    }

    /** @param  array<int, string>  $precios  línea del requerimiento => precio unitario */
    public function agregarCotizacion(int $cuadroId, int $proveedorPartyId, int $plazoDias, string $formaPago, ?string $evaluacion, array $precios): CotizacionProveedor
    {
        $cuadro = CuadroComparativo::query()->findOrFail($cuadroId);

        if ($cuadro->estado !== 'abierto') {
            throw new RuntimeException('El cuadro ya se adjudicó.');
        }

        return DB::transaction(function () use ($cuadro, $proveedorPartyId, $plazoDias, $formaPago, $evaluacion, $precios): CotizacionProveedor {
            $c = $cuadro->cotizaciones()->create(['proveedor_party_id' => $proveedorPartyId, 'plazo_dias' => $plazoDias, 'forma_pago' => $formaPago, 'evaluacion' => $evaluacion]);

            foreach ($precios as $lineaId => $precio) {
                $c->precios()->create(['requerimiento_linea_id' => $lineaId, 'precio_unitario' => $precio]);
            }

            return $c;
        });
    }

    /** Adjudica con motivo obligatorio: la orden hereda líneas, imputaciones y los precios elegidos. */
    public function adjudicar(int $cuadroId, int $cotizacionId, string $motivo, ?string $lugar, ?CarbonImmutable $entrega, User $quien): Orden
    {
        if (trim($motivo) === '') {
            throw new RuntimeException('El motivo es obligatorio: dentro de seis meses alguien va a preguntar por qué se le compró a ese proveedor.');
        }

        return DB::transaction(function () use ($cuadroId, $cotizacionId, $motivo, $lugar, $entrega): Orden {
            $cuadro = CuadroComparativo::query()->lockForUpdate()->findOrFail($cuadroId);
            $cot = CotizacionProveedor::query()->with('precios')->findOrFail($cotizacionId);
            $req = Requerimiento::query()->findOrFail($cuadro->requerimiento_id);
            $cuadro->update(['estado' => 'adjudicado', 'cotizacion_elegida_id' => $cot->id, 'motivo' => trim($motivo)]);
            $lineas = [];

            foreach ($cot->precios as $p) {
                $l = RequerimientoLinea::query()->with('tipo')->findOrFail($p->requerimiento_linea_id);
                $lineas[] = ['requerimiento_linea_id' => $l->id, 'producto_id' => $l->producto_id, 'descripcion' => $l->descripcion, 'unidad_medida_id' => $l->unidad_medida_id,
                    'cantidad' => (string) $l->cantidad, 'precio_unitario' => (string) $p->precio_unitario, 'partida_control_id' => $l->partida_control_id, 'motivo_id' => $l->motivo_id, 'tipo' => $l->tipo];
                $l->update(['estado' => 'ordenada']);
            }

            $tipo = $lineas[0]['tipo'];

            return $this->guardarOrden($tipo, $cot->proveedor_party_id, (string) DB::table('parties')->where('id', $cot->proveedor_party_id)->value('razon_social'),
                $req->id, $cuadro->id, $req->proyecto_id, $cot->forma_pago, $lugar, $entrega ?? Calendario::ahora()->addDays($cot->plazo_dias), $lineas);
        });
    }

    /**
     * Una orden sin requerimiento ni cuadro (servicios recurrentes, la comisión de una carta fianza, compras menores).
     *
     * @param  list<array{descripcion: string, unidad_medida_id: int, cantidad: string, precio_unitario: string, partida_control_id?: ?int, centro_costo_id?: ?int, motivo_id?: ?int, comentario?: ?string, producto_id?: ?int}>  $lineas
     */
    public function ordenDirecta(string $tipo, ?int $proveedorPartyId, string $proveedorNombre, ?int $proyectoId, ?string $formaPago, array $lineas, ?CarbonImmutable $entrega = null): Orden
    {
        return DB::transaction(fn (): Orden => $this->guardarOrden($this->tipo($tipo), $proveedorPartyId, $proveedorNombre, null, null, $proyectoId, $formaPago, null, $entrega, $lineas));
    }

    /** Lo que excede cada partida si se aprueba la orden: [código => exceso]. */
    public function excesos(Orden $orden): array
    {
        $porPartida = [];

        foreach ($orden->lineas as $l) {
            if ($l->partida_control_id !== null) {
                $porPartida[$l->partida_control_id] = bcadd($porPartida[$l->partida_control_id] ?? '0', (string) $l->subtotal, 2);
            }
        }

        $excesos = [];

        foreach ($porPartida as $partidaId => $monto) {
            $p = DB::table('partidas_control')->where('id', $partidaId)->first(['codigo', 'es_no_presupuestado']);
            $disponible = app(PartidasService::class)->saldo($partidaId)->disponible;

            if (! $p->es_no_presupuestado && bccomp($monto, $disponible, 2) > 0) {
                $excesos[$p->codigo] = bcsub($monto, $disponible, 2);
            }
        }

        return $excesos;
    }

    /** Al aprobarse, el monto de cada línea pasa a comprometido en su partida (o centro de costo). El exceso exige motivo. */
    public function aprobarOrden(int $ordenId, ?string $motivoExceso, User $quien): Orden
    {
        return DB::transaction(function () use ($ordenId, $motivoExceso, $quien): Orden {
            $orden = Orden::query()->with('lineas')->lockForUpdate()->findOrFail($ordenId);

            if ($orden->estado !== 'borrador') {
                throw new RuntimeException("La orden {$orden->codigo} ya está ".mb_strtolower(self::ESTADOS_ORDEN[$orden->estado]).'.');
            }

            $excesos = $this->excesos($orden);

            if ($excesos !== [] && trim((string) $motivoExceso) === '') {
                throw new RuntimeException(implode(', ', array_map(fn (string $c, string $m): string => "excede {$c} en S/ ".number_format((float) $m, 2), array_keys($excesos), $excesos)).': la aprobación exige motivo.');
            }

            foreach ($orden->lineas as $l) {
                $this->comprometer($l, (string) $l->subtotal);
            }

            $orden->update(['estado' => 'aprobada', 'motivo_exceso' => $excesos === [] ? null : $motivoExceso, 'aprobado_por' => $quien->id, 'aprobado_at' => Calendario::ahora()]);

            return $orden->refresh();
        });
    }

    /**
     * Recepción en obra o conformidad del servicio: el costo de lo recibido pasa a ejecutado. En almacén (sin
     * imputación a proyecto), entra al stock a su costo.
     *
     * @param  array<int, string>  $cantidades  línea de la orden => cantidad recibida
     */
    public function recibir(int $ordenId, array $cantidades, CarbonImmutable $fecha, ?string $guia, string $lugar = 'obra', ?string $observacion = null): Recepcion
    {
        return DB::transaction(function () use ($ordenId, $cantidades, $fecha, $guia, $lugar, $observacion): Recepcion {
            $orden = Orden::query()->with('lineas')->lockForUpdate()->findOrFail($ordenId);

            if (! in_array($orden->estado, ['aprobada', 'recibida_parcial'], true)) {
                throw new RuntimeException('Solo se recibe una orden aprobada.');
            }

            $rec = Recepcion::query()->create(['codigo' => $this->numero('recepcion', 'REC'), 'orden_id' => $orden->id, 'fecha' => $fecha->toDateString(), 'guia' => $guia, 'lugar' => $lugar, 'observacion' => $observacion]);

            foreach ($orden->lineas as $l) {
                $cantidad = (string) ($cantidades[$l->id] ?? '0');

                if (bccomp($cantidad, '0', 3) <= 0) {
                    continue;
                }

                if (bccomp(bcadd((string) $l->cantidad_recibida, $cantidad, 3), (string) $l->cantidad, 3) > 0) {
                    throw new RuntimeException("{$l->descripcion}: se recibe más de lo pedido.");
                }

                DB::table('recepcion_lineas')->insert(['recepcion_id' => $rec->id, 'orden_linea_id' => $l->id, 'cantidad' => $cantidad]);
                $l->update(['cantidad_recibida' => bcadd((string) $l->cantidad_recibida, $cantidad, 3)]);

                if ($lugar === 'almacen' && $l->producto_id !== null) {
                    $this->ingresoStock((int) $l->producto_id, $cantidad, (string) $l->precio_unitario, $orden->codigo, $fecha);
                }

                if ($l->partida_control_id !== null || $l->centro_costo_id !== null) {
                    $ejecutado = bccomp($l->cantidad_recibida, (string) $l->cantidad, 3) === 0
                        ? bcsub((string) $l->subtotal, (string) app(ImputacionService::class)->de($l)?->monto_ejecutado, 2)
                        : bcadd(bcmul($cantidad, (string) $l->precio_unitario, 4), '0.005', 2);
                    app(ImputacionService::class)->mover($l, MomentoImputacion::Ejecutado, $ejecutado);
                }
            }

            $completa = $orden->lineas->every(fn (OrdenLinea $l): bool => bccomp((string) $l->refresh()->cantidad_recibida, (string) $l->cantidad, 3) === 0);
            $orden->update(['estado' => $completa ? 'recibida' : 'recibida_parcial']);

            return $rec;
        });
    }

    /** MVP rápido: el pago de lo ejecutado (hasta que Tesorería pague de verdad, tramo 5). */
    public function pagar(int $ordenId): void
    {
        foreach (Orden::query()->findOrFail($ordenId)->lineas as $l) {
            $imp = app(ImputacionService::class)->de($l);
            $falta = $imp === null ? '0' : bcsub((string) $imp->monto_ejecutado, (string) $imp->monto_pagado, 2);

            if (bccomp($falta, '0', 2) > 0) {
                app(ImputacionService::class)->mover($l, MomentoImputacion::Pagado, $falta);
            }
        }
    }

    public function ingresoStock(int $productoId, string $cantidad, string $costo, string $documento, CarbonImmutable $fecha): void
    {
        $central = Almacen::query()->where('tipo', 'central')->firstOrFail();
        $s = DB::table('stock')->where('almacen_id', $central->id)->where('producto_id', $productoId)->lockForUpdate()->first();
        $nueva = bcadd((string) ($s->cantidad ?? '0'), $cantidad, 3);
        $promedio = $s === null || bccomp($nueva, '0', 3) === 0 ? $costo
            : bcdiv(bcadd(bcmul((string) $s->cantidad, (string) $s->costo_promedio, 6), bcmul($cantidad, $costo, 6), 6), $nueva, 4);
        DB::table('stock')->updateOrInsert(['almacen_id' => $central->id, 'producto_id' => $productoId], ['cantidad' => $nueva, 'costo_promedio' => $promedio, 'updated_at' => now(), 'created_at' => $s?->created_at ?? now()]);
        $this->movimiento($central->id, $productoId, 'ingreso', $cantidad, $costo, $documento, null, $fecha);
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function guardarOrden(TipoCompra $tipo, ?int $proveedorPartyId, string $proveedorNombre, ?int $reqId, ?int $cuadroId, ?int $proyectoId, ?string $formaPago, ?string $lugar, ?CarbonImmutable $entrega, array $lineas): Orden
    {
        $clase = $tipo->es_bien ? 'compra' : 'servicio';
        $orden = Orden::query()->create([
            'codigo' => $this->numero('orden', $clase === 'compra' ? 'OC' : 'OS'), 'clase' => $clase, 'tipo_compra_id' => $tipo->id,
            'proveedor_party_id' => $proveedorPartyId, 'proveedor_nombre' => $proveedorNombre, 'requerimiento_id' => $reqId, 'cuadro_id' => $cuadroId,
            'proyecto_id' => $proyectoId, 'estado' => 'borrador', 'fecha' => Calendario::hoy(), 'entrega_pactada' => $entrega?->toDateString(),
            'lugar_entrega' => $lugar, 'forma_pago' => $formaPago,
        ]);

        foreach ($lineas as $l) {
            $orden->lineas()->create([
                'requerimiento_linea_id' => $l['requerimiento_linea_id'] ?? null, 'producto_id' => $l['producto_id'] ?? null, 'descripcion' => $l['descripcion'],
                'unidad_medida_id' => $l['unidad_medida_id'], 'cantidad' => $l['cantidad'], 'precio_unitario' => $l['precio_unitario'],
                'subtotal' => bcadd(bcmul((string) $l['cantidad'], (string) $l['precio_unitario'], 4), '0.005', 2),
                'partida_control_id' => $l['partida_control_id'] ?? null, 'centro_costo_id' => $l['centro_costo_id'] ?? null,
                'motivo_id' => $l['motivo_id'] ?? null, 'comentario' => $l['comentario'] ?? null,
            ]);
        }

        return $orden;
    }

    /** El monto comprometido de una línea (de orden o de requerimiento) en su partida o centro de costo. */
    private function comprometer(Model $linea, string $monto): void
    {
        $partidaId = $linea->getAttribute('partida_control_id');
        $destino = $partidaId !== null
            ? app(PartidasService::class)->destino((int) $partidaId, $linea->getAttribute('motivo_id'), $linea->getAttribute('comentario') ?? ($linea->getAttribute('motivo_id') === null ? null : $linea->getAttribute('descripcion')))
            : new CentroDeCosto((int) $linea->getAttribute('centro_costo_id'));
        $imputaciones = app(ImputacionService::class);
        $imputaciones->destinar($linea, $destino, Calendario::ahora(), 'PEN');
        $imputaciones->mover($linea, MomentoImputacion::Comprometido, $monto);
    }

    private function cerrarSiAtendido(Requerimiento $req): void
    {
        if ($req->lineas()->whereNotIn('estado', ['despachada', 'ordenada'])->doesntExist()) {
            $req->update(['estado' => 'atendido']);
        }
    }

    private function movimiento(int $almacenId, int $productoId, string $tipo, string $cantidad, string $costo, string $documento, ?int $proyectoId, ?CarbonImmutable $fecha = null): void
    {
        DB::table('movimientos_almacen')->insert(['almacen_id' => $almacenId, 'producto_id' => $productoId, 'tipo' => $tipo, 'cantidad' => $cantidad, 'costo_unitario' => $costo,
            'documento' => $documento, 'proyecto_id' => $proyectoId, 'fecha' => ($fecha ?? Calendario::ahora())->toDateString(), 'created_by' => auth()->id() ?? 1, 'created_at' => now()]);
    }

    private function tipo(string $codigo): TipoCompra
    {
        return TipoCompra::query()->where('codigo', $codigo)->first() ?? throw new RuntimeException("Tipo de compra desconocido: {$codigo}.");
    }

    /** @param  list<string>  $estados */
    private function req(int $id, array $estados): Requerimiento
    {
        $req = Requerimiento::query()->with('lineas.tipo')->lockForUpdate()->findOrFail($id);

        if (! in_array($req->estado, $estados, true)) {
            throw new RuntimeException("El requerimiento {$req->codigo} está ".mb_strtolower(self::ESTADOS_REQ[$req->estado]).'.');
        }

        return $req;
    }

    private function numero(string $tipo, string $serie): string
    {
        return app(NumeracionService::class)->siguiente($tipo, new FormatoNumeracion($serie), Calendario::ahora());
    }
}
