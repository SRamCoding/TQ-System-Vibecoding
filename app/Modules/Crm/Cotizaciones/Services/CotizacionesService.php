<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Efectos\PedidoAceptacion;
use App\Modules\Crm\Cotizaciones\Enums\OrigenLinea;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Erp\Presupuestos\Services\PresupuestoNoEncontrado;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * La cotización (plan 2.22): crear, editar el borrador y sus líneas, versión nueva, pedir aprobación, emitir y aceptar.
 * Cada cambio bloquea la versión, compara la versión leída y recalcula los totales (`CalculoCotizacion`); cada cambio
 * de estado pasa por `TransicionesService`. Oportunidades, Cuentas, el catálogo (C4) y el presupuesto (C8) se leen por
 * sus servicios (regla 2). Lo que usa un solo método se pide en ese método (AGENTS.md: así no cierra ciclos).
 */
final readonly class CotizacionesService
{
    public const string PERMISO_CREAR = 'crm.cotizaciones.crear';

    public const string PERMISO_EDITAR = 'crm.cotizaciones.editar';

    public const string PERMISO_NUEVA_VERSION = 'crm.cotizaciones.nueva-version';

    /** D21: sobre las cotizaciones de cualquier oportunidad (Gerencia Comercial); sin él, solo las de las propias. */
    public const string PERMISO_TODAS = 'crm.cotizaciones.todas';

    public const string PARAMETRO_IGV = 'tributario.igv_porcentaje';

    private const string MAXIMO_MONTO = '9999999999.9999';

    public function crear(int $oportunidadId, DatosCotizacion $datos, User $quien): Cotizacion
    {
        return DB::transaction(function () use ($oportunidadId, $datos, $quien): Cotizacion {
            // La oportunidad bloqueada (codigo.md: quien crea un dependiente bloquea su origen).
            $oportunidad = $this->oportunidadUsable($oportunidadId, $quien, bloquear: true);
            $cuenta = app(CuentasService::class)->resumen($oportunidad->cuentaId);
            $encabezado = $this->encabezado($datos, $cuenta->partyId, $oportunidad);

            return app(TransicionesService::class)->crear(new Cotizacion([
                ...$encabezado,
                'numero_version' => 1,
                'oportunidad_id' => $oportunidad->id,
                'cuenta_id' => $oportunidad->cuentaId,
            ]), $quien)->refresh();
        });
    }

    /** El encabezado y las condiciones del borrador. La moneda cambia solo sin líneas (todas van en la moneda de la versión). */
    public function editar(int $cotizacionId, int $versionLeida, DatosCotizacion $datos, User $quien): Cotizacion
    {
        return $this->conBorrador($cotizacionId, $versionLeida, $quien, function (Cotizacion $c) use ($datos): void {
            $oportunidad = app(OportunidadesService::class)->resumen($c->oportunidad_id);
            $encabezado = $this->encabezado($datos, app(CuentasService::class)->resumen($c->cuenta_id)->partyId, $oportunidad);

            if ($encabezado['moneda_id'] !== $c->moneda_id && $c->lineas()->exists()) {
                throw CotizacionRechazada::conMotivo('La moneda cambia solo sin líneas: todas van en la moneda de la cotización.');
            }

            $c->fill($encabezado);
        });
    }

    /** Una línea del catálogo (foto de su descripción y unidad) o libre, «por catalogar» (D6). */
    public function agregarLinea(int $cotizacionId, int $versionLeida, DatosLinea $datos, User $quien): LineaDeCotizacion
    {
        $this->validarMontos($datos->cantidad, $datos->precioUnitario, $datos->costoUnitario, $datos->descuentoPct);
        [$origen, $descripcion, $unidadId] = $this->origenDe($datos);
        $linea = null;

        $this->conBorrador($cotizacionId, $versionLeida, $quien, function (Cotizacion $c) use ($datos, $origen, $descripcion, $unidadId, &$linea): void {
            $linea = $c->lineas()->create([
                'origen' => $origen, 'producto_id' => $datos->productoId, 'descripcion' => $descripcion, 'unidad_medida_id' => $unidadId,
                'cantidad' => $datos->cantidad, 'precio_unitario' => $datos->precioUnitario, 'descuento_porcentaje' => $datos->descuentoPct,
                'costo_unitario' => $datos->costoUnitario, 'subtotal' => '0', 'orden' => (int) $c->lineas()->max('orden') + 1,
            ]);
        });

        return $linea->refresh();
    }

    /**
     * Enlaza el presupuesto con APU (C8, D8) como una línea: precio = costo directo + gastos generales + utilidad; costo
     * = costo directo + gastos generales (P4). Uno por versión: si ya había otro, lo reemplaza.
     */
    public function enlazarPresupuesto(int $cotizacionId, int $versionLeida, int $presupuestoId, string $descuentoPct, User $quien): Cotizacion
    {
        $this->validarMontos('1', '0', '0', $descuentoPct);

        return $this->conBorrador($cotizacionId, $versionLeida, $quien, function (Cotizacion $c) use ($presupuestoId, $descuentoPct): void {
            $presupuesto = $this->presupuestoEnlazable($c, $presupuestoId);
            $c->lineas()->where('origen', OrigenLinea::Presupuesto->value)->get()->each->delete();
            $c->presupuesto_id = $presupuesto->id;
            $c->lineas()->create([
                'origen' => OrigenLinea::Presupuesto, ...$this->datosDelPresupuesto($presupuesto), 'cantidad' => '1',
                'descuento_porcentaje' => $descuentoPct, 'subtotal' => '0', 'orden' => (int) $c->lineas()->max('orden') + 1,
            ]);
        });
    }

    /** Cantidad, precio, descuento y costo de una línea. La del presupuesto solo cambia su descuento (lo demás sale de C8). */
    public function editarLinea(int $lineaId, int $versionLeida, DatosLinea $datos, User $quien): Cotizacion
    {
        $this->validarMontos($datos->cantidad, $datos->precioUnitario, $datos->costoUnitario, $datos->descuentoPct);
        $linea = LineaDeCotizacion::query()->find($lineaId) ?? throw CotizacionRechazada::conMotivo('Esa línea no existe.');

        return $this->conBorrador($linea->cotizacion_id, $versionLeida, $quien, function () use ($lineaId, $datos): void {
            $linea = LineaDeCotizacion::query()->lockForUpdate()->findOrFail($lineaId);
            $linea->descuento_porcentaje = $datos->descuentoPct;

            if ($linea->origen !== OrigenLinea::Presupuesto) {
                $linea->fill(['cantidad' => $datos->cantidad, 'precio_unitario' => $datos->precioUnitario, 'costo_unitario' => $datos->costoUnitario]);
            }

            $linea->version++;
            $linea->save();
        });
    }

    public function quitarLinea(int $lineaId, int $versionLeida, User $quien): Cotizacion
    {
        $linea = LineaDeCotizacion::query()->find($lineaId) ?? throw CotizacionRechazada::conMotivo('Esa línea no existe.');

        return $this->conBorrador($linea->cotizacion_id, $versionLeida, $quien, function (Cotizacion $c) use ($lineaId): void {
            $linea = LineaDeCotizacion::query()->lockForUpdate()->findOrFail($lineaId);

            if ($linea->origen === OrigenLinea::Presupuesto) {
                $c->presupuesto_id = null;
            }

            $linea->delete();
        });
    }

    /**
     * La versión siguiente (D5): sale solo de la última, si ya salió al cliente (emitida, rechazada o vencida). Copia
     * encabezado, condiciones y líneas a un borrador; el presupuesto enlazado se vuelve a leer (otros costos: una versión
     * nueva del presupuesto, que se enlaza aquí). Lleva su motivo de la lista.
     */
    public function nuevaVersion(int $cotizacionId, int $motivoId, ?string $detalle, User $quien): Cotizacion
    {
        return DB::transaction(function () use ($cotizacionId, $motivoId, $detalle, $quien): Cotizacion {
            $origen = Cotizacion::query()->with('estado')->find($cotizacionId) ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');
            // Primero la oportunidad (codigo.md: quien crea un dependiente bloquea su origen; el mismo orden que aceptar),
            // después las versiones.
            $this->oportunidadUsable($origen->oportunidad_id, $quien, bloquear: true);
            $versiones = Cotizacion::query()->where('numero', $origen->numero)->lockForUpdate()->get();
            $origen = $versiones->firstWhere('id', $origen->id)->load('estado');
            $ultima = (int) $versiones->max('numero_version');

            if ($origen->numero_version !== $ultima) {
                throw CotizacionRechazada::conMotivo("La versión nueva sale de la última: {$origen->numero} v{$ultima}.");
            }

            if (! in_array($origen->estado->codigo, DefinicionCotizacion::SALE_VERSION_NUEVA, true)) {
                throw CotizacionRechazada::conMotivo("{$origen->numero} v{$origen->numero_version} está ".mb_strtolower($origen->estado->nombre).': la versión nueva sale de una emitida, rechazada o vencida.');
            }

            $motivo = $this->motivoDeVersion($motivoId, $detalle);

            try {
                $nueva = app(TransicionesService::class)->crear(new Cotizacion([
                    ...$origen->only(['numero', 'oportunidad_id', 'cuenta_id', 'contacto_id', 'moneda_id', 'presupuesto_id', 'dias_validez',
                        'plazo_entrega_dias', 'forma_pago_id', 'forma_pago_detalle', 'garantia_meses', 'lugar_entrega']),
                    'numero_version' => $ultima + 1,
                    'motivo_version_id' => $motivo->id,
                    'motivo_version_detalle' => $detalle === null || trim($detalle) === '' ? null : trim($detalle),
                ]), $quien);
            } catch (UniqueConstraintViolationException) {
                throw CotizacionRechazada::conMotivo("Otra persona acaba de crear la versión nueva de {$origen->numero}: ábrela.");
            }

            foreach ($origen->lineas as $linea) {
                $nueva->lineas()->create($linea->only(['origen', 'producto_id', 'descripcion', 'unidad_medida_id', 'cantidad', 'precio_unitario',
                    'descuento_porcentaje', 'costo_unitario', 'subtotal', 'margen_porcentaje', 'orden']));
            }

            // Si el presupuesto de la anterior ya no se enlaza (reemplazado o aprobado), la línea queda como foto: al pedir
            // aprobación o emitir se pide enlazar su versión vigente (enlazarPresupuesto la reemplaza).
            try {
                $this->releerPresupuesto($nueva);
            } catch (CotizacionRechazada) {
                // La línea queda como estaba.
            }
            $this->recalcular($nueva);

            return $nueva->refresh();
        });
    }

    /** Pide la aprobación de Gerencia Comercial (D14): relee el presupuesto, recalcula y deja con qué mínimo se midió. */
    public function solicitarAprobacion(int $cotizacionId, int $versionLeida, ?string $comentario, User $quien): Cotizacion
    {
        return $this->transicionarDesdeBorrador($cotizacionId, $versionLeida, DefinicionCotizacion::SOLICITAR_APROBACION, $comentario, $quien);
    }

    /** Emite (D5, D9, D10): desde borrador, relee y recalcula antes; desde aprobada, con los números que se firmaron. */
    public function emitir(int $cotizacionId, int $versionLeida, ?string $comentario, User $quien): Cotizacion
    {
        return $this->transicionarDesdeBorrador($cotizacionId, $versionLeida, DefinicionCotizacion::EMITIR, $comentario, $quien);
    }

    /**
     * El cliente la acepta (D5, P2): fecha (no futura, no antes de emitirla ni después de vencer) y el documento de
     * respaldo (tipo y referencia; el archivo, opcional).
     */
    public function aceptar(int $cotizacionId, int $versionLeida, CarbonImmutable $fecha, TipoRespaldo $tipo, string $referencia, ?int $adjuntoId, ?string $comentario, User $quien, ?ArchivoSubido $archivo = null): Cotizacion
    {
        if (trim($referencia) === '' || mb_strlen(trim($referencia)) > 100) {
            throw CotizacionRechazada::conMotivo('Escribe el número o la referencia del documento de respaldo (hasta 100 caracteres).');
        }

        if ($fecha->toDateString() > Calendario::hoy()) {
            throw CotizacionRechazada::conMotivo('La fecha de aceptación no puede ser futura.');
        }

        if ($archivo !== null && $adjuntoId !== null) {
            throw CotizacionRechazada::conMotivo('Sube el archivo del respaldo o elige uno ya adjunto, no los dos.');
        }

        return DB::transaction(function () use ($cotizacionId, $versionLeida, $fecha, $tipo, $referencia, $adjuntoId, $comentario, $quien, $archivo): Cotizacion {
            // Primero la oportunidad (no se cruza con anularla ni perderla), después la cotización.
            $this->bloquearOportunidadDe($cotizacionId);
            $c = $this->bloquear($cotizacionId, $versionLeida);

            // Plan 2.22b: el respaldo es un adjunto de esta cotización (subido aquí, o uno que ya tiene).
            $adjuntos = app(AdjuntosService::class);

            if ($archivo !== null) {
                $adjuntoId = $adjuntos->agregar($c->getMorphClass(), $c->id, $archivo, null, $quien)->id;
            } elseif ($adjuntoId !== null && ! $adjuntos->vigentes($c, $quien)->contains('id', $adjuntoId)) {
                throw CotizacionRechazada::conMotivo('Ese archivo no está adjunto a esta cotización.');
            }

            if ($c->estado->codigo === DefinicionCotizacion::EMITIDA) {
                if ($fecha->toDateString() < $c->fecha_emision?->toDateString()) {
                    throw CotizacionRechazada::conMotivo('La fecha de aceptación no puede ser anterior a la emisión.');
                }

                if ($fecha->toDateString() > $c->fecha_vencimiento?->toDateString()) {
                    throw CotizacionRechazada::conMotivo("{$c->numero} v{$c->numero_version} venció el {$c->fecha_vencimiento?->format('d/m/Y')}: haz una versión nueva.");
                }
            }

            // El efecto de «aceptar» pone la fecha, el respaldo y la marca juntos (la base lo exige).
            app(PedidoAceptacion::class)->durante($fecha, $tipo, trim($referencia), $adjuntoId, fn () => app(TransicionesService::class)
                ->transicionar($c, DefinicionCotizacion::ACEPTAR, $versionLeida, $quien, null, $comentario));

            return $c->refresh();
        });
    }

    /** Anula la aceptación (D5): bloquea la oportunidad antes, para no cruzarse con ganar. Con motivo de su lista. */
    public function anularAceptacion(int $cotizacionId, int $versionLeida, MotivoElegido $motivo, ?string $comentario, User $quien): Cotizacion
    {
        return DB::transaction(function () use ($cotizacionId, $versionLeida, $motivo, $comentario, $quien): Cotizacion {
            $this->bloquearOportunidadDe($cotizacionId);
            $c = $this->bloquear($cotizacionId, $versionLeida);
            app(TransicionesService::class)->transicionar($c, DefinicionCotizacion::ANULAR_ACEPTACION, $versionLeida, $quien, $motivo, $comentario);

            return $c->refresh();
        });
    }

    /** La tarea diaria (D10): las emitidas cuyo vencimiento ya pasó, a vencidas. Devuelve cuántas. */
    public function vencerLasVencidas(): int
    {
        $ids = Cotizacion::query()
            ->whereHas('estado', fn ($q) => $q->where('codigo', DefinicionCotizacion::EMITIDA))
            ->where('fecha_vencimiento', '<', Calendario::hoy())
            ->pluck('id');

        $vencidas = 0;

        foreach ($ids as $id) {
            $vencidas += DB::transaction(function () use ($id): int {
                $c = Cotizacion::query()->with('estado')->lockForUpdate()->findOrFail($id);

                if ($c->estado->codigo !== DefinicionCotizacion::EMITIDA) {
                    return 0;
                }

                app(TransicionesService::class)->transicionarComoSistema($c, DefinicionCotizacion::VENCER);

                return 1;
            });
        }

        return $vencidas;
    }

    /** C3 (D12): una versión, con sus condiciones como texto y sus líneas, sin costo ni margen. */
    public function resumen(int $cotizacionId): ResumenCotizacion
    {
        $c = Cotizacion::query()->with(['estado', 'lineas'])->find($cotizacionId) ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');
        $unidades = UnidadMedida::query()->whereIn('id', $c->lineas->pluck('unidad_medida_id'))->pluck('abreviatura', 'id');
        $formaPago = array_filter([
            $c->forma_pago_id === null ? null : (string) FormaPago::query()->whereKey($c->forma_pago_id)->value('nombre'),
            $c->forma_pago_detalle,
        ]);

        return new ResumenCotizacion(
            $c->id, $c->numero, $c->numero_version, "{$c->numero} v{$c->numero_version}", $c->estado->codigo, $c->oportunidad_id, $c->cuenta_id,
            (string) Moneda::query()->whereKey($c->moneda_id)->value('codigo'), (string) $c->subtotal, (string) $c->igv, (string) $c->total,
            $c->fecha_emision?->toDateString(), $c->fecha_vencimiento?->toDateString(), $c->es_aceptada,
            $c->plazo_entrega_dias === null ? null : "{$c->plazo_entrega_dias} días calendario",
            $formaPago === [] ? null : implode(' · ', $formaPago),
            $c->garantia_meses === null ? null : "{$c->garantia_meses} meses",
            $c->lugar_entrega, $c->presupuesto_id,
            $c->lineas->map(fn (LineaDeCotizacion $l): array => [
                'descripcion' => $l->descripcion, 'unidad' => (string) ($unidades[$l->unidad_medida_id] ?? ''), 'cantidad' => (string) $l->cantidad,
                'precio_unitario' => (string) $l->precio_unitario, 'descuento_porcentaje' => (string) $l->descuento_porcentaje, 'subtotal' => (string) $l->subtotal,
            ])->values()->all(),
        );
    }

    /** D21: el ejecutivo de la oportunidad, o quien puede sobre todas. */
    public function puedeSobre(User $usuario, int $oportunidadId): bool
    {
        return app(SeguridadService::class)->puede($usuario, self::PERMISO_TODAS)
            || app(OportunidadesService::class)->resumen($oportunidadId)->ejecutivoUserId === $usuario->id;
    }

    /**
     * Calcula líneas y totales y los pone en la versión (sin guardarla). Con `$lineas`, también guarda el subtotal y el
     * margen de cada línea (solo en borrador: después, las líneas no cambian). IGV: el congelado o el del parámetro.
     */
    public function ponerTotales(Cotizacion $c, bool $lineas = false): void
    {
        $filas = $c->lineas()->get();
        $totales = CalculoCotizacion::calcular(
            $filas->map(fn (LineaDeCotizacion $l): LineaParaCalculo => new LineaParaCalculo((string) $l->cantidad, (string) $l->precio_unitario, (string) $l->descuento_porcentaje, (string) $l->costo_unitario))->all(),
            (string) ($c->igv_porcentaje ?? app(ParametrosService::class)->decimal(self::PARAMETRO_IGV)),
        );

        if ($lineas) {
            foreach ($filas->values() as $i => $fila) {
                $fila->subtotal = $totales->lineas[$i]->subtotal;
                $fila->margen_porcentaje = $totales->lineas[$i]->margenPct;

                if ($fila->isDirty()) {
                    $fila->save();
                }
            }
        }

        $c->fill([
            'subtotal' => $totales->subtotal, 'descuento_total' => $totales->descuento, 'igv' => $totales->igv,
            'total' => $totales->total, 'costo_total' => $totales->costo, 'margen_porcentaje' => $totales->margenPct,
        ]);
    }

    private function transicionarDesdeBorrador(int $cotizacionId, int $versionLeida, string $codigo, ?string $comentario, User $quien): Cotizacion
    {
        return DB::transaction(function () use ($cotizacionId, $versionLeida, $codigo, $comentario, $quien): Cotizacion {
            // Primero la oportunidad, como en aceptar: emitir no se cruza con perderla o anularla (ni deja el presupuesto
            // ofertado sobre una cerrada).
            $this->bloquearOportunidadDe($cotizacionId);
            $c = $this->bloquear($cotizacionId, $versionLeida);

            if ($c->estado->codigo === DefinicionCotizacion::APROBADA && $codigo === DefinicionCotizacion::EMITIR) {
                $this->exigirPresupuestoComoSeAprobo($c);
            }

            if ($c->estado->codigo === DefinicionCotizacion::BORRADOR) {
                $this->exigirSobre($c, $quien);
                $this->releerPresupuesto($c);
                $parametros = app(ParametrosService::class);
                $c->margen_minimo = $parametros->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO);
                $c->margen_objetivo = $parametros->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_OBJETIVO);
                $this->recalcular($c);
            }

            app(TransicionesService::class)->transicionar($c, $codigo, $c->version, $quien, null, $comentario);

            return $c->refresh();
        });
    }

    /**
     * Bloquea el borrador, comprueba versión, dueño y oportunidad abierta, hace el cambio y recalcula.
     *
     * @param  Closure(Cotizacion): void  $cambio
     */
    private function conBorrador(int $cotizacionId, int $versionLeida, User $quien, Closure $cambio): Cotizacion
    {
        return DB::transaction(function () use ($cotizacionId, $versionLeida, $quien, $cambio): Cotizacion {
            $c = $this->bloquear($cotizacionId, $versionLeida);

            if ($c->estado->codigo !== DefinicionCotizacion::BORRADOR) {
                throw CotizacionRechazada::conMotivo("{$c->numero} v{$c->numero_version} está ".mb_strtolower($c->estado->nombre).': no se edita'
                    .($c->estado->codigo === DefinicionCotizacion::APROBADA ? ' (vuelve a borrador para cambiarla).' : ' (los cambios van en una versión nueva).'));
            }

            $this->oportunidadUsable($c->oportunidad_id, $quien);
            $cambio($c);
            $this->recalcular($c);

            return $c->refresh();
        });
    }

    private function bloquear(int $cotizacionId, int $versionLeida): Cotizacion
    {
        $c = Cotizacion::query()->with('estado')->lockForUpdate()->find($cotizacionId)
            ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');

        if ($c->version !== $versionLeida) {
            throw VersionDesactualizada::de("la cotización {$c->numero} v{$c->numero_version}");
        }

        return $c;
    }

    /** Recalcula (líneas y totales) y sube la versión: una transición pedida con números viejos no pasa. */
    private function recalcular(Cotizacion $c): void
    {
        $this->ponerTotales($c, lineas: true);
        $c->version++;
        $c->save();
    }

    /**
     * Desde aprobada se emiten los números que se firmaron: si el presupuesto enlazado cambió desde entonces, lo que se
     * ofertaría no sería lo aprobado (revisión de arquitectura).
     */
    private function exigirPresupuestoComoSeAprobo(Cotizacion $c): void
    {
        if ($c->presupuesto_id === null) {
            return;
        }

        $linea = $c->lineas()->where('origen', OrigenLinea::Presupuesto->value)->first();
        $ahora = $this->datosDelPresupuesto($this->presupuestoEnlazable($c, $c->presupuesto_id));

        if ($linea === null || bccomp((string) $linea->precio_unitario, $ahora['precio_unitario'], 4) !== 0 || bccomp((string) $linea->costo_unitario, $ahora['costo_unitario'], 4) !== 0) {
            throw CotizacionRechazada::conMotivo('El presupuesto enlazado cambió desde que se aprobó la cotización: vuelve a borrador y pide la aprobación otra vez.');
        }
    }

    /** D8: mientras es borrador, la línea del presupuesto se relee de C8. */
    private function releerPresupuesto(Cotizacion $c): void
    {
        if ($c->presupuesto_id === null) {
            return;
        }

        $presupuesto = $this->presupuestoEnlazable($c, $c->presupuesto_id);
        $linea = $c->lineas()->where('origen', OrigenLinea::Presupuesto->value)->first();
        $linea?->fill($this->datosDelPresupuesto($presupuesto));

        if ($linea?->isDirty()) {
            $linea->version++;
            $linea->save();
        }
    }

    private function presupuestoEnlazable(Cotizacion $c, int $presupuestoId): ResumenPresupuesto
    {
        try {
            $presupuesto = app(PresupuestosService::class)->resumen($presupuestoId);
        } catch (PresupuestoNoEncontrado) {
            throw CotizacionRechazada::conMotivo('Ese presupuesto no existe.');
        }

        $moneda = (string) Moneda::query()->whereKey($c->moneda_id)->value('codigo');
        $nombre = "El presupuesto {$presupuesto->numero} v{$presupuesto->numeroVersion}";

        return match (true) {
            $presupuesto->oportunidadId !== $c->oportunidad_id => throw CotizacionRechazada::conMotivo("{$nombre} es de otra oportunidad."),
            $presupuesto->monedaCodigo !== $moneda => throw CotizacionRechazada::conMotivo("{$nombre} está en {$presupuesto->monedaCodigo} y la cotización en {$moneda}."),
            ! in_array($presupuesto->estado, ['borrador', 'ofertado'], true) => throw CotizacionRechazada::conMotivo("{$nombre} está ".mb_strtolower($presupuesto->estadoNombre).': enlaza su versión vigente.'),
            default => $presupuesto,
        };
    }

    /** @return array<string, mixed> */
    private function datosDelPresupuesto(ResumenPresupuesto $p): array
    {
        $costo = bcadd($p->costoDirecto, $p->gastosGenerales, 2);

        return [
            'descripcion' => "{$p->nombre} (presupuesto {$p->numero} v{$p->numeroVersion})",
            'unidad_medida_id' => UnidadMedida::query()->where('codigo', 'GLB')->value('id')
                ?? throw CotizacionRechazada::conMotivo('Falta la unidad «GLB» (global) en el catálogo de unidades: la línea del presupuesto va en ella.'),
            'precio_unitario' => bcadd($costo, $p->utilidad, 2),
            'costo_unitario' => $costo,
        ];
    }

    /** @return array{OrigenLinea, string, int} */
    private function origenDe(DatosLinea $datos): array
    {
        if ($datos->productoId !== null) {
            try {
                $producto = app(CatalogoService::class)->resumen($datos->productoId);
            } catch (CatalogoRechazado) {
                $producto = null;
            }

            if ($producto === null || ! $producto->esActivo) {
                throw CotizacionRechazada::conMotivo('Ese producto no está activo en el catálogo.');
            }

            return [OrigenLinea::Catalogo, trim($producto->descripcion) !== '' ? $producto->descripcion : $producto->nombre, $producto->unidadMedidaId];
        }

        $descripcion = trim((string) $datos->descripcion);

        if ($descripcion === '' || mb_strlen($descripcion) > 500 || $datos->unidadMedidaId === null || ! UnidadMedida::query()->whereKey($datos->unidadMedidaId)->exists()) {
            throw CotizacionRechazada::conMotivo('Una línea por catalogar lleva su descripción (hasta 500 caracteres) y su unidad.');
        }

        return [OrigenLinea::Libre, $descripcion, $datos->unidadMedidaId];
    }

    private function validarMontos(string $cantidad, string $precio, string $costo, string $descuento): void
    {
        foreach ([$cantidad, $precio, $costo, $descuento] as $valor) {
            if (! is_numeric($valor) || bccomp($valor, '0', 4) < 0 || bccomp($valor, self::MAXIMO_MONTO, 4) > 0) {
                throw CotizacionRechazada::conMotivo('Cantidades, precios y costos van sin signo.');
            }
        }

        if (bccomp($cantidad, '0', 3) <= 0) {
            throw CotizacionRechazada::conMotivo('La cantidad tiene que ser mayor que cero.');
        }

        if (bccomp($descuento, '100', 2) > 0) {
            throw CotizacionRechazada::conMotivo('El descuento va de 0 a 100 %.');
        }
    }

    /** @return array<string, mixed> */
    private function encabezado(DatosCotizacion $d, int $partyId, ResumenOportunidad $oportunidad): array
    {
        $monedaId = $d->monedaId ?? (int) Moneda::query()->where('codigo', $oportunidad->moneda)->value('id');
        $dias = $d->diasValidez ?? app(ParametrosService::class)->entero(CotizacionesServiceProvider::PARAMETRO_DIAS_VALIDEZ);

        $error = match (true) {
            ! Moneda::query()->whereKey($monedaId)->whereIn('codigo', ['PEN', 'USD'])->exists() => 'La cotización va en soles o en dólares.',
            $dias < 1 || $dias > 365 => 'Los días de validez van de 1 a 365.',
            $d->contactoId !== null && ! PartyContacto::query()->whereKey($d->contactoId)->where('party_id', $partyId)->exists() => 'Ese contacto no es de la cuenta.',
            $d->formaPagoId !== null && ! FormaPago::query()->whereKey($d->formaPagoId)->exists() => 'Esa forma de pago no existe.',
            ($d->plazoEntregaDias ?? 0) < 0 || ($d->garantiaMeses ?? 0) < 0 => 'El plazo y la garantía no pueden ser negativos.',
            mb_strlen((string) $d->formaPagoDetalle) > 200 || mb_strlen((string) $d->lugarEntrega) > 200 => 'El detalle de la forma de pago y el lugar de entrega van hasta 200 caracteres.',
            default => null,
        };

        if ($error !== null) {
            throw CotizacionRechazada::conMotivo($error);
        }

        $texto = fn (?string $t): ?string => $t === null || trim($t) === '' ? null : trim($t);

        return [
            'contacto_id' => $d->contactoId, 'moneda_id' => $monedaId, 'dias_validez' => $dias, 'plazo_entrega_dias' => $d->plazoEntregaDias,
            'forma_pago_id' => $d->formaPagoId, 'forma_pago_detalle' => $texto($d->formaPagoDetalle), 'garantia_meses' => $d->garantiaMeses,
            'lugar_entrega' => $texto($d->lugarEntrega),
        ];
    }

    /** La oportunidad existe, está abierta y quien cotiza es su ejecutivo o puede sobre todas (D4, D21). */
    private function oportunidadUsable(int $oportunidadId, User $quien, bool $bloquear = false): ResumenOportunidad
    {
        $oportunidades = app(OportunidadesService::class);
        $oportunidad = $bloquear ? $oportunidades->bloquearParaDependiente($oportunidadId) : $oportunidades->resumen($oportunidadId);

        if (! $oportunidad->abierta) {
            throw CotizacionRechazada::conMotivo("La oportunidad {$oportunidad->numero} ya está cerrada: no se cotiza sobre ella.");
        }

        if (! $this->puedeSobre($quien, $oportunidadId)) {
            throw CotizacionRechazada::conMotivo('Solo el ejecutivo de la oportunidad, o quien puede sobre todas las cotizaciones, cotiza sobre ella.');
        }

        return $oportunidad;
    }

    private function bloquearOportunidadDe(int $cotizacionId): void
    {
        $oportunidadId = Cotizacion::query()->whereKey($cotizacionId)->value('oportunidad_id')
            ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');
        app(OportunidadesService::class)->bloquearParaDependiente((int) $oportunidadId);
    }

    private function exigirSobre(Cotizacion $c, User $quien): void
    {
        $this->oportunidadUsable($c->oportunidad_id, $quien);
    }

    private function motivoDeVersion(int $motivoId, ?string $detalle): Motivo
    {
        $motivo = Motivo::query()->whereKey($motivoId)->where('es_activo', true)
            ->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', CotizacionesServiceProvider::FAMILIA_NUEVA_VERSION))->first()
            ?? throw CotizacionRechazada::conMotivo('Elige el motivo de la versión nueva de su lista.');

        if ($motivo->es_otro && trim((string) $detalle) === '') {
            throw CotizacionRechazada::conMotivo('Con «Otro», escribe el motivo.');
        }

        return $motivo;
    }
}
