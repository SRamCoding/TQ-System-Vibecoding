<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\OrdenDeSala;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\Secciones\SeccionDeOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\DetalleDeOtroRechazado;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\TipoCambioFaltante;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Lo que se hace con una oportunidad (plan 2.21). La lógica vive aquí; las acciones solo llaman. Los cambios de etapa
 * pasan por TransicionesService (permiso, condiciones, motivo, historial y efectos); aquí se guardan los datos que cada
 * paso pide, en la misma transacción. Orden de bloqueo (C3): la oportunidad primero, la cuenta al final.
 */
final readonly class OportunidadesService
{
    public const string PERMISO_CREAR = 'crm.oportunidades.crear';

    public const string PERMISO_REASIGNAR = 'crm.oportunidades.reasignar';

    /** El tipo de persona o empresa que reciben los competidores (D4). */
    public const string ROL_COMPETIDOR = 'competidor';

    /** Las transiciones que avanzan una etapa (D1). */
    public const array AVANCES = ['calificar', 'enviar_propuesta', 'negociar'];

    /** Los órdenes de la tabla de un lugar de la faja (plan 2.21b, D9): lista fija, nunca una columna que llega. */
    public const array ORDENES_FAJA = ['codigo', 'nombre', 'monto', 'dias', 'cierre'];

    /** El tope de filas por página de esas tablas. */
    public const int MAXIMO_POR_PAGINA_FAJA = 100;

    /**
     * Nace en Prospecto en una cuenta usable (D6: bloqueo compartido de la cuenta), con el origen y el ejecutivo de la
     * cuenta si no se dan (D5, D7). Las secciones de otros módulos guardan lo suyo en la misma transacción (D12.4).
     *
     * @param  array<string, mixed>  $datosSecciones
     */
    public function crear(int $cuentaId, DatosOportunidad $datos, array $datosSecciones, User $quien): ResumenOportunidad
    {
        $id = DB::transaction(function () use ($cuentaId, $datos, $datosSecciones, $quien): int {
            $cuenta = app(CuentasService::class)->exigirUsable($cuentaId);
            $ejecutivo = $datos->ejecutivoUserId ?? $cuenta->ejecutivoUserId;
            $this->validarEjecutivo($ejecutivo);
            $this->validarMonto($datos->montoEstimado);
            $this->validarDecisor($cuenta->partyId, $datos->decisorContactoId);
            // Plan 2.0, D5: el detalle de «Otro» va con su origen: el elegido, o el de la cuenta con el suyo.
            $origenDetalle = $datos->origenComercialId === null
                ? self::sinDetallePropio($datos, $cuenta->origenDetalle)
                : app(CatalogosService::class)->detalleDeOtro(Catalogo::OrigenesComerciales, $datos->origenComercialId, $datos->origenDetalle, 'el origen');

            $oportunidad = new Oportunidad([
                'cuenta_id' => $cuentaId,
                'nombre' => trim($datos->nombre),
                'descripcion' => self::texto($datos->descripcion),
                'monto_estimado' => $datos->montoEstimado,
                'moneda_id' => $datos->monedaId,
                'fecha_cierre_estimada' => $datos->fechaCierreEstimada->toDateString(),
                'en_etapa_desde' => Calendario::hoy(),
                'origen_comercial_id' => $datos->origenComercialId ?? $cuenta->origenComercialId,
                'origen_detalle' => $origenDetalle,
                'ejecutivo_user_id' => $ejecutivo,
                'linea_solucion_id' => $datos->lineaSolucionId,
                'necesidad_confirmada' => $datos->necesidadConfirmada,
                'presupuesto_confirmado' => $datos->presupuestoConfirmado,
                'decisor_contacto_id' => $datos->decisorContactoId,
            ]);
            app(TransicionesService::class)->crear($oportunidad, $quien);
            app(SeccionesDeOportunidad::class)->guardar($oportunidad->id, SeccionDeOportunidad::AL_CREAR, $datosSecciones, $quien);

            return $oportunidad->id;
        });

        return $this->resumen($id);
    }

    /** Edita los datos de una abierta (D10: todas o las propias). La cuenta y el ejecutivo no se cambian aquí. */
    public function editar(int $oportunidadId, int $versionLeida, DatosOportunidad $datos, User $quien): ResumenOportunidad
    {
        DB::transaction(function () use ($oportunidadId, $versionLeida, $datos, $quien): void {
            $oportunidad = $this->bloquearAbiertaPropia($oportunidadId, $versionLeida, $quien);
            $this->validarMonto($datos->montoEstimado);
            $this->validarDecisor($this->partyDeLaCuenta($oportunidad), $datos->decisorContactoId);
            // Plan 2.0, D5: sin origen nuevo, se queda con el suyo y su detalle.
            $origenDetalle = $datos->origenComercialId === null
                ? self::sinDetallePropio($datos, $oportunidad->origen_detalle)
                : app(CatalogosService::class)->detalleDeOtro(Catalogo::OrigenesComerciales, $datos->origenComercialId, $datos->origenDetalle, 'el origen');

            $oportunidad->fill([
                'nombre' => trim($datos->nombre),
                'descripcion' => self::texto($datos->descripcion),
                'monto_estimado' => $datos->montoEstimado,
                'moneda_id' => $datos->monedaId,
                'fecha_cierre_estimada' => $datos->fechaCierreEstimada->toDateString(),
                'linea_solucion_id' => $datos->lineaSolucionId,
                'origen_comercial_id' => $datos->origenComercialId ?? $oportunidad->origen_comercial_id,
                'origen_detalle' => $origenDetalle,
                'necesidad_confirmada' => $datos->necesidadConfirmada,
                'presupuesto_confirmado' => $datos->presupuestoConfirmado,
                'decisor_contacto_id' => $datos->decisorContactoId,
            ]);
            $this->guardarConNuevaVersion($oportunidad);
        });

        return $this->resumen($oportunidadId);
    }

    /**
     * Pasa una o varias abiertas a otro ejecutivo (D5), todo o nada. Con una sola, compara la versión leída. Las
     * cerradas no cambian de ejecutivo: la atribución no se mueve.
     *
     * @param  list<int>  $oportunidadIds
     */
    public function reasignar(array $oportunidadIds, int $ejecutivoUserId, ?int $versionLeida = null): int
    {
        return DB::transaction(function () use ($oportunidadIds, $ejecutivoUserId, $versionLeida): int {
            $this->validarEjecutivo($ejecutivoUserId);
            $cambiadas = 0;
            // Por id ascendente, como el traspaso: dos que bloquean las mismas filas no se traban entre sí.
            $ids = array_values(array_unique($oportunidadIds));
            sort($ids);

            foreach ($ids as $id) {
                $oportunidad = $this->bloquear($id, count($ids) === 1 ? $versionLeida : null);
                $this->exigirAbierta($oportunidad);

                if ($oportunidad->ejecutivo_user_id !== $ejecutivoUserId) {
                    $oportunidad->ejecutivo_user_id = $ejecutivoUserId;
                    $this->guardarConNuevaVersion($oportunidad);
                    $cambiadas++;
                }
            }

            return $cambiadas;
        });
    }

    /**
     * Al reasignar la cuenta con «pasar también sus oportunidades abiertas» (D5): pasa al ejecutivo actual de la cuenta
     * solo las abiertas cuyo ejecutivo era el anterior; las asignadas a otra persona a propósito se quedan con ella. Sin comparar
     * versiones (solo cambia el ejecutivo) y repetible: la segunda vez no encuentra nada que pasar. Lo hace el sistema.
     */
    public function traspasarDeEjecutivo(int $cuentaId, int $anteriorUserId): int
    {
        return DB::transaction(function () use ($cuentaId, $anteriorUserId): int {
            // El destino es el ejecutivo que la cuenta tiene ahora, no el del evento: si dos reasignaciones seguidas se
            // procesan fuera de orden (un reintento), las oportunidades terminan con el ejecutivo vigente.
            $nuevoUserId = app(CuentasService::class)->resumen($cuentaId)->ejecutivoUserId;

            $abiertas = Oportunidad::query()
                ->where('cuenta_id', $cuentaId)
                ->where('ejecutivo_user_id', $anteriorUserId)
                ->whereHas('estado', fn ($q) => $q->whereIn('codigo', array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases())))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($abiertas as $oportunidad) {
                $oportunidad->ejecutivo_user_id = $nuevoUserId;
                $this->guardarConNuevaVersion($oportunidad);
            }

            return $abiertas->count();
        });
    }

    /**
     * Avanza una etapa (D1, D9): guarda lo que el panel completó (calificación y secciones de otros módulos) y pide la
     * transición, que revisa lo exigido hasta la etapa destino. Si algo falla, no se guarda nada.
     *
     * @param  array<string, mixed>  $datosSecciones
     */
    public function avanzar(int $oportunidadId, int $versionLeida, string $codigo, ?bool $necesidad, ?bool $presupuesto, ?int $decisorContactoId, array $datosSecciones, ?string $comentario, User $quien): HistorialEstado
    {
        if (! in_array($codigo, self::AVANCES, true)) {
            throw OportunidadRechazada::noEsAvance($codigo);
        }

        return DB::transaction(function () use ($oportunidadId, $versionLeida, $codigo, $necesidad, $presupuesto, $decisorContactoId, $datosSecciones, $comentario, $quien): HistorialEstado {
            $oportunidad = $this->bloquearAbiertaPropia($oportunidadId, $versionLeida, $quien);

            if ($decisorContactoId !== null) {
                $this->validarDecisor($this->partyDeLaCuenta($oportunidad), $decisorContactoId);
                $oportunidad->decisor_contacto_id = $decisorContactoId;
            }

            // Plan 2.21d, D8 (revisión de arquitectura: la regla vive aquí, no en la pantalla): confirmar la necesidad
            // pide cómo se confirmó; confirmar el presupuesto, su certificación (en este avance o ya adjunta). Lo
            // cumplido queda con lo que se registró.
            $detalle = trim((string) ($datosSecciones['necesidad_detalle'] ?? ''));
            $certificacion = $datosSecciones['certificacion_presupuestal'] ?? null;

            if ($necesidad === true && ! $oportunidad->necesidad_confirmada && $detalle === '' && $oportunidad->necesidad_detalle === null) {
                throw OportunidadRechazada::conMotivo('Para confirmar la necesidad, di cómo se confirmó (reunión, oficio, correo).');
            }

            if ($presupuesto === true && ! $oportunidad->presupuesto_confirmado && ! $certificacion instanceof ArchivoSubido && ! $this->tieneAdjuntos($oportunidad)) {
                throw OportunidadRechazada::conMotivo('Para confirmar el presupuesto, adjunta la certificación presupuestal o el PIA.');
            }

            $oportunidad->necesidad_confirmada = $necesidad ?? $oportunidad->necesidad_confirmada;
            $oportunidad->presupuesto_confirmado = $presupuesto ?? $oportunidad->presupuesto_confirmado;

            if ($detalle !== '') {
                $oportunidad->necesidad_detalle = $detalle;
            }

            $this->guardarConNuevaVersion($oportunidad);

            if ($certificacion instanceof ArchivoSubido) {
                app(AdjuntosService::class)->agregar($oportunidad->getMorphClass(), $oportunidad->id, $certificacion, null, $quien);
            }
            app(SeccionesDeOportunidad::class)->guardar($oportunidad->id, SeccionDeOportunidad::AL_AVANZAR, $datosSecciones, $quien);

            return app(TransicionesService::class)->transicionar($oportunidad, $codigo, $oportunidad->version, $quien, null, $comentario);
        });
    }

    /** Si la oportunidad tiene algún archivo vigente (la certificación pudo adjuntarse antes, desde sus adjuntos). */
    private function tieneAdjuntos(Oportunidad $oportunidad): bool
    {
        return Adjunto::query()->where('adjuntable_type', $oportunidad->getMorphClass())->where('adjuntable_id', $oportunidad->id)->whereNull('quitado_at')->exists();
    }

    /** Gana (D3): la fecha de cierre real nunca es futura (sí puede ser anterior a la creación) y se confirma el monto. */
    public function ganar(int $oportunidadId, int $versionLeida, CarbonImmutable $fechaCierreReal, ?string $montoFinal, ?string $comentario, User $quien, ?DatosDelProyectoAlGanar $proyecto = null): HistorialEstado
    {
        if ($fechaCierreReal->toDateString() > Calendario::hoy()) {
            throw OportunidadRechazada::fechaCierreFutura();
        }

        return DB::transaction(function () use ($oportunidadId, $versionLeida, $fechaCierreReal, $montoFinal, $comentario, $quien, $proyecto): HistorialEstado {
            $oportunidad = $this->bloquearAbiertaPropia($oportunidadId, $versionLeida, $quien);
            // Plan 2.22, D12 y P5: con cotización aceptada, el monto final es su total con IGV, en su moneda, y no se
            // escribe otro («nadie vuelve a escribir lo que ya se escribió», v4 parte 3).
            $aceptada = app(CotizacionDeOportunidad::class)->aceptada($oportunidadId);

            if ($aceptada !== null) {
                if ($montoFinal !== null && bccomp($montoFinal, $aceptada->total, 2) !== 0) {
                    throw OportunidadRechazada::montoDeLaAceptada($aceptada);
                }

                $montoFinal = $aceptada->total;
                $oportunidad->moneda_id = (int) Moneda::query()->where('codigo', $aceptada->moneda)->value('id');
            }

            $this->validarMonto($montoFinal ?? throw OportunidadRechazada::montoNoPositivo());
            // D6: la cuenta en exclusivo desde ya (marcarCliente igual lo necesita), después de la oportunidad (C3).
            app(CuentasService::class)->exigirUsable($oportunidad->cuenta_id, exclusivo: true);
            $oportunidad->fecha_cierre_real = $fechaCierreReal->startOfDay();
            $oportunidad->monto_estimado = $montoFinal;
            $this->guardarConNuevaVersion($oportunidad);

            // MV-006: el efecto de ganar hace nacer el proyecto con estos datos, en esta misma transacción.
            return app(PedidoGanar::class)->durante($proyecto, fn (): HistorialEstado => app(TransicionesService::class)->transicionar($oportunidad, 'ganar', $oportunidad->version, $quien, null, $comentario));
        });
    }

    /**
     * Pierde (D4): motivo de su lista y «¿Lo ganó otra empresa?». Con «Sí», el competidor (ficha de Personas y
     * empresas, con el tipo «Competidor») y su precio con moneda, o «precio desconocido».
     */
    public function perder(int $oportunidadId, int $versionLeida, MotivoElegido $motivo, GanadorConocido $ganadorConocido, ?int $ganadorPartyId, ?string $precio, ?int $monedaId, bool $precioDesconocido, ?string $comentario, User $quien): HistorialEstado
    {
        $this->validarGanador($ganadorConocido, $ganadorPartyId, $precio, $monedaId, $precioDesconocido);

        return DB::transaction(function () use ($oportunidadId, $versionLeida, $motivo, $ganadorConocido, $ganadorPartyId, $precio, $monedaId, $precioDesconocido, $comentario, $quien): HistorialEstado {
            $oportunidad = $this->bloquearAbiertaPropia($oportunidadId, $versionLeida, $quien);
            $oportunidad->ganador_conocido = $ganadorConocido;
            $oportunidad->fecha_cierre_real = CarbonImmutable::parse(Calendario::hoy());
            $this->guardarConNuevaVersion($oportunidad);

            if ($ganadorConocido === GanadorConocido::Si && $ganadorPartyId !== null) {
                $competidor = OportunidadCompetidor::query()->firstOrNew(['oportunidad_id' => $oportunidad->id, 'party_id' => $ganadorPartyId]);
                $competidor->fill([
                    'precio' => $precioDesconocido ? null : $precio,
                    'moneda_id' => $precioDesconocido ? null : $monedaId,
                    'precio_desconocido' => $precioDesconocido,
                    'es_ganador' => true,
                ]);
                $this->guardarCompetidorConRol($competidor);
            }

            return app(TransicionesService::class)->transicionar($oportunidad, 'perder', $oportunidad->version, $quien, $motivo, $comentario);
        });
    }

    /**
     * Agrega un competidor a una abierta o cambia el que ya está (D4): una ficha de Personas y empresas, nunca texto
     * libre; el precio, opcional, con su moneda. La ficha recibe el tipo «Competidor» si no lo tiene.
     */
    public function guardarCompetidor(int $oportunidadId, ?int $competidorId, ?int $versionCompetidor, int $partyId, ?string $precio, ?int $monedaId, ?string $fortalezas, ?string $debilidades, User $quien): OportunidadCompetidor
    {
        if (($precio === null) !== ($monedaId === null) || ($precio !== null && bccomp($precio, '0', 2) <= 0)) {
            throw OportunidadRechazada::precioIncompleto();
        }

        return DB::transaction(function () use ($oportunidadId, $competidorId, $versionCompetidor, $partyId, $precio, $monedaId, $fortalezas, $debilidades, $quien): OportunidadCompetidor {
            $oportunidad = $this->bloquearAbiertaPropia($oportunidadId, null, $quien);
            $competidor = $competidorId === null
                ? new OportunidadCompetidor(['oportunidad_id' => $oportunidad->id, 'party_id' => $partyId])
                : OportunidadCompetidor::query()->where('oportunidad_id', $oportunidad->id)->lockForUpdate()->findOrFail($competidorId);

            if ($competidorId !== null && $competidor->version !== $versionCompetidor) {
                throw VersionDesactualizada::de('el competidor');
            }

            if ($competidorId === null && OportunidadCompetidor::query()->where('oportunidad_id', $oportunidad->id)->where('party_id', $partyId)->exists()) {
                throw OportunidadRechazada::competidorRepetido();
            }

            $competidor->fill(['precio' => $precio, 'moneda_id' => $monedaId, 'fortalezas' => self::texto($fortalezas), 'debilidades' => self::texto($debilidades)]);
            $this->guardarCompetidorConRol($competidor);

            return $competidor->refresh();
        });
    }

    public function quitarCompetidor(int $competidorId, int $versionLeida, User $quien): void
    {
        DB::transaction(function () use ($competidorId, $versionLeida, $quien): void {
            $oportunidadId = OportunidadCompetidor::query()->whereKey($competidorId)->value('oportunidad_id')
                ?? throw OportunidadRechazada::competidorNoExiste();
            $this->bloquearAbiertaPropia((int) $oportunidadId, null, $quien);
            $competidor = OportunidadCompetidor::query()->lockForUpdate()->findOrFail($competidorId);

            if ($competidor->version !== $versionLeida) {
                throw VersionDesactualizada::de('el competidor');
            }

            $competidor->delete();
        });
    }

    /**
     * Para Contratos (C3, aprobada 2026-11-07): antes de crear el contrato de una oportunidad ganada, su oyente llama esto
     * dentro de su transacción. Bloquea la oportunidad (primera en el orden de bloqueo de la C3) y dice si sigue ganada;
     * si se anuló antes de que el oyente corriera, no se crea nada. Mientras el oyente tenga el bloqueo, nadie la anula.
     */
    public function exigirGanada(int $oportunidadId): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('exigirGanada bloquea la oportunidad: se llama dentro de la transacción de quien la usa.');
        }

        $oportunidad = Oportunidad::query()->with('estado')->lockForUpdate()->find($oportunidadId);

        return $oportunidad?->estado->codigo === 'ganada';
    }

    /**
     * Para el selector de otro módulo (C13; plan 2.21, D12): las abiertas por número o nombre, sin tildes ni mayúsculas,
     * de una cuenta si se da; con `incluirId`, la que el registro ya tiene aunque esté cerrada (AGENTS.md).
     *
     * @return list<ResumenOportunidad>
     */
    public function buscar(string $texto, ?int $cuentaId = null, ?int $incluirId = null, int $limite = 20): array
    {
        $ids = Oportunidad::query()->abiertas()
            ->when($cuentaId !== null, fn ($q) => $q->where('cuenta_id', $cuentaId))
            ->buscarTexto(trim($texto), ['numero', 'nombre'])
            ->orderByDesc('id')
            ->limit($limite)
            ->pluck('id')
            ->all();

        if ($incluirId !== null && ! in_array($incluirId, $ids, true) && Oportunidad::query()->whereKey($incluirId)->exists()) {
            array_unshift($ids, $incluirId);
        }

        return array_map(fn (int $id): ResumenOportunidad => $this->resumen($id), $ids);
    }

    /**
     * Total y ponderado de un conjunto de oportunidades abiertas, en moneda local (D2, D8): ponderado = monto ×
     * probabilidad de su etapa (parámetro), con bcmath. Otra moneda, al T.C. de compra del día (o el último dentro de la
     * tolerancia); sin T.C., aparte. Suma en la base por moneda y etapa: no trae filas a PHP.
     *
     * @param  Builder<Oportunidad>  $consulta
     */
    public function embudo(Builder $consulta): EmbudoEnSoles
    {
        $local = app(ParametrosService::class)->monedaLocal();
        $grupos = (clone $consulta)->abiertas()
            ->join('monedas', 'monedas.id', '=', 'oportunidades.moneda_id')
            ->join('estados', 'estados.id', '=', 'oportunidades.estado_id')
            ->groupBy('monedas.codigo', 'estados.codigo')
            ->selectRaw('monedas.codigo AS moneda, estados.codigo AS etapa, count(*) AS cantidad, sum(oportunidades.monto_estimado) AS monto')
            ->toBase()->get();

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $total = '0';
        $ponderado = '0';
        $cantidad = 0;
        $convertido = [];
        $sinTipoCambio = [];
        $tasas = [];

        foreach ($grupos as $grupo) {
            $moneda = (string) $grupo->moneda;
            $monto = (string) $grupo->monto;

            if ($moneda !== $local && ! array_key_exists($moneda, $tasas)) {
                try {
                    $tasas[$moneda] = app(TipoCambioService::class)->del($moneda, $hoy)->compra;
                } catch (TipoCambioFaltante) {
                    $tasas[$moneda] = null;
                }
            }

            $tasa = $moneda === $local ? '1' : $tasas[$moneda];

            if ($tasa === null) {
                $sinTipoCambio[$moneda] = [
                    'monto' => bcadd($sinTipoCambio[$moneda]['monto'] ?? '0', $monto, 2),
                    'cantidad' => ($sinTipoCambio[$moneda]['cantidad'] ?? 0) + (int) $grupo->cantidad,
                ];

                continue;
            }

            if ($moneda !== $local) {
                $convertido[$moneda] = ['monto' => bcadd($convertido[$moneda]['monto'] ?? '0', $monto, 2), 'tipoCambio' => $tasa];
            }

            $enLocal = bcmul($monto, $tasa, 4);
            $probabilidad = (string) app(ParametrosService::class)->entero(EtapaAbierta::from((string) $grupo->etapa)->parametroProbabilidad());
            $total = bcadd($total, $enLocal, 4);
            $ponderado = bcadd($ponderado, bcdiv(bcmul($enLocal, $probabilidad, 4), '100', 4), 4);
            $cantidad += (int) $grupo->cantidad;
        }

        return new EmbudoEnSoles($cantidad, bcadd($total, '0', 2), bcadd($ponderado, '0', 2), $convertido, $sinTipoCambio);
    }

    /**
     * La tabla de un lugar de la faja de vidrio (plan 2.21b, D9; pedido B5): una zona (sus abiertas en esa etapa) o una
     * sala (las ganadas o perdidas del período de la columna «Cierre» del tablero, `cerradasDelPeriodo`). Sin anuladas.
     * Busca por número o nombre, sin tildes ni mayúsculas. «monto» ordena en moneda local al T.C. de compra de hoy (lo
     * que no tiene T.C., al final); «dias» por días en la etapa; «cierre» por el cierre estimado en una zona y por el
     * real en una sala. Empata por id en la misma dirección. Todos ven todas: ver oportunidades no se limita a las
     * propias (D10 de la 2.21 limita editar, no ver). `$soloDe` (un usuario) deja solo las de ese ejecutivo: «Mías» en
     * Ganadas y Perdidas del tablero de gemas (plan 2.21c, D15).
     */
    public function tablaDeLaFaja(LugarFaja $lugar, ?string $busqueda = null, string $orden = 'codigo', string $direccion = 'asc', int $pagina = 1, int $porPagina = 25, ?int $soloDe = null): PaginaFaja
    {
        if (! in_array($orden, self::ORDENES_FAJA, true) || ! in_array($direccion, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Orden de la faja no admitido: {$orden} {$direccion}.");
        }

        $pagina = max(1, $pagina);
        $porPagina = max(1, min($porPagina, self::MAXIMO_POR_PAGINA_FAJA));
        $consulta = $this->enLugarDeLaFaja($lugar, $soloDe)->buscarTexto(trim((string) $busqueda), ['oportunidades.numero', 'oportunidades.nombre']);
        $total = (clone $consulta)->count();
        $tasas = $this->tasasDeCompra($consulta);

        match ($orden) {
            'codigo' => $consulta->orderBy('oportunidades.numero', $direccion),
            'nombre' => $consulta->orderBy('oportunidades.nombre', $direccion),
            'monto' => $this->ordenarPorMontoEnSoles($consulta, $tasas, $direccion),
            // Más días es entrar antes a la etapa.
            'dias' => $consulta->orderBy('oportunidades.en_etapa_desde', $direccion === 'asc' ? 'desc' : 'asc'),
            'cierre' => $consulta->orderBy($lugar->esSala() ? 'oportunidades.fecha_cierre_real' : 'oportunidades.fecha_cierre_estimada', $direccion),
        };

        $oportunidades = $consulta->orderBy('oportunidades.id', $direccion)
            ->with(['estado', 'moneda', 'ejecutivo:id,name'])
            ->forPage($pagina, $porPagina)
            ->get();
        $filas = $this->filasDeLaFaja($oportunidades, $tasas);

        return new PaginaFaja($filas, $total, $pagina, $porPagina);
    }

    /**
     * Cuántas hay en cada lugar de la faja y su total en moneda local (plan 2.21b; pedido B5: viñetas, rótulos y
     * terminales), con las mismas reglas que `tablaDeLaFaja`. En el orden de los lugares.
     *
     * @return array<string, TotalLugarFaja> por valor de LugarFaja
     */
    public function totalesDeLaFaja(): array
    {
        $totales = [];

        foreach (LugarFaja::cases() as $lugar) {
            $totales[$lugar->value] = $this->totalDeLugarDeLaFaja($lugar);
        }

        return $totales;
    }

    /**
     * Lo mismo que `totalesDeLaFaja` para un solo lugar (plan 2.21b, paso 9): la ventana de un lugar no consulta los seis.
     * Con `$soloDe`, solo las de ese ejecutivo («Mías», plan 2.21c, D15).
     */
    public function totalDeLugarDeLaFaja(LugarFaja $lugar, ?int $soloDe = null): TotalLugarFaja
    {
        $consulta = $this->enLugarDeLaFaja($lugar, $soloDe);
        $tasas = $this->tasasDeCompra($consulta);
        $grupos = $consulta->groupBy('oportunidades.moneda_id')
            ->selectRaw('oportunidades.moneda_id AS moneda_id, count(*) AS cantidad, sum(oportunidades.monto_estimado) AS monto')
            ->toBase()->get();
        $cantidad = 0;
        $sinTipoCambio = 0;
        $total = '0';

        foreach ($grupos as $grupo) {
            $cantidad += (int) $grupo->cantidad;
            $tasa = $tasas[(int) $grupo->moneda_id] ?? null;

            if ($tasa === null) {
                $sinTipoCambio += (int) $grupo->cantidad;
            } else {
                $total = bcadd($total, bcmul((string) $grupo->monto, $tasa, 4), 4);
            }
        }

        return new TotalLugarFaja($lugar, $cantidad, bcadd($total, '0', 2), $sinTipoCambio);
    }

    /**
     * Las que se ven de un lugar en el tablero de gemas (plan 2.21c, D2), con las mismas filas y reglas que
     * `tablaDeLaFaja`: en una zona, primero las atascadas (pasan los días máximos de su etapa, como
     * `PortadaOportunidades::pasaElMaximo`) y luego las de mayor monto en moneda local (sin T.C., al final). Empata por
     * id, la más antigua primero. En una sala manda `$ordenSala` (D15): las más recientes (cierre real y luego id, de
     * la última a la primera), las de mayor monto (como una zona) o, en Perdidas, por motivo de pérdida (el motivo más
     * frecuente de las del período primero; dentro de un motivo, las más recientes). Con `$soloDe`, solo las de ese
     * ejecutivo («Mías»).
     *
     * @return list<FilaFaja>
     */
    public function destacadasDeLaFaja(LugarFaja $lugar, int $cuantas, OrdenDeSala $ordenSala = OrdenDeSala::Recientes, ?int $soloDe = null): array
    {
        if ($cuantas < 1) {
            return [];
        }

        $consulta = $this->enLugarDeLaFaja($lugar, $soloDe);
        $tasas = $this->tasasDeCompra($consulta);

        if ($lugar->esSala()) {
            $this->ordenarSala($consulta, $lugar, $ordenSala, $tasas);
            $oportunidades = $consulta->with(['estado', 'moneda', 'ejecutivo:id,name'])->limit($cuantas)->get();

            return $this->filasDeLaFaja($oportunidades, $tasas);
        }

        $etapa = $lugar->etapa();
        $maximo = $etapa === null ? 0 : app(PortadaOportunidades::class)->diasMaximos($etapa);

        if ($maximo > 0) {
            // Más días que el máximo es haber entrado antes de hoy menos el máximo (la regla de `estancadas`).
            $limite = CarbonImmutable::parse(Calendario::hoy())->subDays($maximo)->toDateString();
            $consulta->orderByRaw('CASE WHEN oportunidades.en_etapa_desde < ? THEN 0 ELSE 1 END', [$limite]);
        }

        $this->ordenarPorMontoEnSoles($consulta, $tasas, 'desc');
        $oportunidades = $consulta->orderBy('oportunidades.id')
            ->with(['estado', 'moneda', 'ejecutivo:id,name'])
            ->limit($cuantas)
            ->get();

        return $this->filasDeLaFaja($oportunidades, $tasas);
    }

    /**
     * Todas las abiertas como filas de la faja, por fecha de cierre estimada y luego por id (plan 2.21c, D9: la línea de
     * tiempo). Las cerradas y las anuladas no tienen cierre pendiente.
     *
     * @return list<FilaFaja>
     */
    public function abiertasPorCierreEstimado(): array
    {
        $consulta = Oportunidad::query()->abiertas();
        $tasas = $this->tasasDeCompra($consulta);
        $oportunidades = $consulta->orderBy('oportunidades.fecha_cierre_estimada')->orderBy('oportunidades.id')
            ->with(['estado', 'moneda', 'ejecutivo:id,name'])
            ->get();

        return $this->filasDeLaFaja($oportunidades, $tasas);
    }

    /**
     * Una oportunidad como la ve la faja (plan 2.21b, paso 6: el despiece de su caja), con los mismos datos y reglas que
     * una fila de `tablaDeLaFaja`, esté en una zona o en una sala. Una anulada no está en la faja (D11.2 de la 2.21):
     * como si no existiera.
     */
    public function filaDeLaFaja(int $oportunidadId): FilaFaja
    {
        $consulta = app(PortadaOportunidades::class)->sinAnuladas(Oportunidad::query()->whereKey($oportunidadId));
        $oportunidad = (clone $consulta)->with(['estado', 'moneda', 'ejecutivo:id,name'])->firstOrFail();

        return $this->filasDeLaFaja(new EloquentCollection([$oportunidad]), $this->tasasDeCompra($consulta))[0];
    }

    /**
     * La versión de una oportunidad que está en la faja (plan 2.21b, paso 9), para comparar con la tomada al agarrar su
     * caja; nula si no existe o está anulada (D11.2 de la 2.21: las anuladas no están en ningún lugar).
     */
    public function versionEnLaFaja(int $oportunidadId): ?int
    {
        $version = app(PortadaOportunidades::class)->sinAnuladas(Oportunidad::query()->whereKey($oportunidadId))->value('version');

        return is_numeric($version) ? (int) $version : null;
    }

    /**
     * Por qué la persona no puede mover esta oportunidad, aparte del permiso de la acción; nulo si puede (plan 2.21b,
     * paso 9). Es la regla que revisan avanzar, ganar y perder al ejecutarse (`bloquearAbiertaPropia`, D10 de la 2.21:
     * todas, o las propias si es su ejecutivo), con su mismo mensaje. Un aviso anticipado: quien decide sigue siendo la
     * acción.
     */
    public function rechazoAlMoverla(int $oportunidadId, User $quien): ?string
    {
        $ejecutivo = Oportunidad::query()->whereKey($oportunidadId)->value('ejecutivo_user_id');

        return is_numeric($ejecutivo) && $this->puedeEditarla($quien, (int) $ejecutivo)
            ? null
            : OportunidadRechazada::sinPermisoParaEditar()->getMessage();
    }

    /**
     * Los resúmenes de varias oportunidades en una consulta, por id (para las carpetas de Cotizaciones, plan 2.22b). Las que
     * no existen no vienen.
     *
     * @param  list<int>  $ids
     * @return array<int, ResumenOportunidad>
     */
    public function resumenesDe(array $ids): array
    {
        return Oportunidad::query()->with(['estado', 'moneda'])->whereKey(array_values(array_unique($ids)))->get()
            ->mapWithKeys(fn (Oportunidad $o): array => [$o->id => new ResumenOportunidad(
                $o->id, $o->numero, $o->nombre, $o->cuenta_id, $o->ejecutivo_user_id, $o->estado->codigo, (string) $o->monto_estimado,
                $o->moneda->codigo, EtapaAbierta::tryFrom($o->estado->codigo) !== null, $o->version,
            )])->all();
    }

    /**
     * Los ids de las oportunidades de un ejecutivo, abiertas o no («Solo mías» de otro módulo, plan 2.22b).
     *
     * @return list<int>
     */
    public function idsDelEjecutivo(int $userId): array
    {
        return Oportunidad::query()->where('ejecutivo_user_id', $userId)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function resumen(int $oportunidadId): ResumenOportunidad
    {
        return $this->resumenesDe([$oportunidadId])[$oportunidadId]
            ?? throw (new ModelNotFoundException)->setModel(Oportunidad::class, [$oportunidadId]);
    }

    /**
     * Las filas de la faja de unas oportunidades (con estado, moneda y ejecutivo): la cuenta por CuentasService, el
     * contrato de las ganadas por ContratosDeOportunidad y el motivo y el ganador de las perdidas.
     *
     * @param  EloquentCollection<int, Oportunidad>  $oportunidades
     * @param  array<int, ?string>  $tasas  por id de moneda
     * @return list<FilaFaja>
     */
    private function filasDeLaFaja(EloquentCollection $oportunidades, array $tasas): array
    {
        $deEstado = fn (string $codigo): array => $oportunidades->filter(fn (Oportunidad $o): bool => $o->estado->codigo === $codigo)->pluck('id')->values()->all();
        $ganadas = $deEstado('ganada');
        $perdidas = $deEstado('perdida');
        $cuentas = app(CuentasService::class)->resumenesDe($oportunidades->pluck('cuenta_id')->all());
        $contratos = $ganadas === [] ? [] : app(ContratosDeOportunidad::class)->etiquetasDeContratos($ganadas);
        $motivos = $perdidas === [] ? [] : $this->motivosDePerdida($perdidas);
        $ganadores = $perdidas === [] ? [] : $this->ganadoresDe($perdidas);
        $portada = app(PortadaOportunidades::class);
        // MVP rápido (t1-agenda): la próxima acción, de Actividades.
        $proximas = app(ProximaAccionDeOportunidades::class)->deLote($oportunidades->pluck('id')->all());

        return $oportunidades->map(function (Oportunidad $o) use ($cuentas, $contratos, $motivos, $ganadores, $tasas, $portada, $proximas): FilaFaja {
            $dias = $portada->diasEnEtapa($o);
            $etapa = EtapaAbierta::tryFrom($o->estado->codigo);
            $maximo = $etapa === null ? 0 : $portada->diasMaximos($etapa);
            $tasa = $tasas[$o->moneda_id] ?? null;
            $ganador = $ganadores[$o->id] ?? null;

            return new FilaFaja(
                id: $o->id,
                numero: $o->numero,
                nombre: $o->nombre,
                etapa: $o->estado->codigo,
                cuenta: $cuentas[$o->cuenta_id]->etiquetaParaSelector(),
                monto: (string) $o->monto_estimado,
                moneda: $o->moneda->codigo,
                simbolo: $o->moneda->simbolo,
                montoEnSoles: $tasa === null ? null : bcadd(bcmul((string) $o->monto_estimado, $tasa, 4), '0', 2),
                ejecutivo: $o->ejecutivo->name,
                diasEnEtapa: $dias,
                atascada: $maximo > 0 && $dias > $maximo,
                proximaAccion: isset($proximas[$o->id]) ? ($proximas[$o->id]['vencida'] ? 'Vencida · ' : '').$proximas[$o->id]['texto'] : null,
                cierreEstimado: $o->fecha_cierre_estimada,
                version: $o->version,
                fechaCierre: $o->fecha_cierre_real,
                contrato: $contratos[$o->id] ?? null,
                motivo: $motivos[$o->id] ?? null,
                ganadorConocido: $o->ganador_conocido,
                ganador: $ganador['nombre'] ?? null,
                precio: $ganador['precio'] ?? null,
                precioSimbolo: $ganador['simbolo'] ?? null,
                precioDesconocido: $ganador['desconocido'] ?? false,
            );
        })->values()->all();
    }

    /**
     * @return Builder<Oportunidad>
     */
    private function enLugarDeLaFaja(LugarFaja $lugar, ?int $soloDe = null): Builder
    {
        $portada = app(PortadaOportunidades::class);
        $consulta = $portada->enEstado(Oportunidad::query(), $lugar->estado());

        if ($soloDe !== null) {
            $consulta->where('oportunidades.ejecutivo_user_id', $soloDe);
        }

        return $lugar->esSala() ? $portada->cerradasDelPeriodo($consulta) : $consulta;
    }

    /**
     * El orden de las que se ven en una sala (plan 2.21c, D15). «Por motivo» solo vale en Perdidas: el motivo es el de su
     * último paso a «perdida» del historial (como `motivosDePerdida`); cuántas comparten motivo se cuenta entre las de la
     * consulta (el período y, con «Mías», solo las propias). Las que no tienen motivo, al final.
     *
     * @param  Builder<Oportunidad>  $consulta
     * @param  array<int, ?string>  $tasas
     */
    private function ordenarSala(Builder $consulta, LugarFaja $sala, OrdenDeSala $orden, array $tasas): void
    {
        if (! in_array($orden, OrdenDeSala::de($sala), true)) {
            throw new InvalidArgumentException("{$sala->etiqueta()} no se ordena «{$orden->etiqueta()}».");
        }

        if ($orden === OrdenDeSala::Monto) {
            $this->ordenarPorMontoEnSoles($consulta, $tasas, 'desc');
            $consulta->orderBy('oportunidades.id');

            return;
        }

        if ($orden === OrdenDeSala::Motivo) {
            $tipo = (new Oportunidad)->getMorphClass();
            $perdida = (int) Estado::query()->where('documento_type', $tipo)->where('codigo', LugarFaja::Perdidas->estado())->value('id');
            $motivo = '(SELECT h.motivo_id FROM historial_estados h WHERE h.documentable_type = ? AND h.documentable_id = oportunidades.id'
                .' AND h.hacia_estado_id = ? ORDER BY h.id DESC LIMIT 1)';
            $consulta->orderByRaw("{$motivo} IS NULL", [$tipo, $perdida])
                ->orderByRaw("count(*) OVER (PARTITION BY {$motivo}) DESC", [$tipo, $perdida])
                ->orderByRaw("{$motivo} ASC NULLS LAST", [$tipo, $perdida]);
        }

        $consulta->orderByDesc('oportunidades.fecha_cierre_real')->orderByDesc('oportunidades.id');
    }

    /**
     * El T.C. de compra de hoy de cada moneda de la consulta, como el embudo (D8); la local vale 1 y sin T.C. es nulo.
     *
     * @param  Builder<Oportunidad>  $consulta
     * @return array<int, ?string> por id de moneda
     */
    private function tasasDeCompra(Builder $consulta): array
    {
        $monedaIds = (clone $consulta)->reorder()->distinct()->pluck('oportunidades.moneda_id')->all();
        $local = app(ParametrosService::class)->monedaLocal();
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $tasas = [];

        foreach (Moneda::query()->whereKey($monedaIds)->pluck('codigo', 'id') as $id => $codigo) {
            try {
                $tasas[(int) $id] = $codigo === $local ? '1' : app(TipoCambioService::class)->del((string) $codigo, $hoy)->compra;
            } catch (TipoCambioFaltante) {
                $tasas[(int) $id] = null;
            }
        }

        return $tasas;
    }

    /**
     * @param  Builder<Oportunidad>  $consulta
     * @param  array<int, ?string>  $tasas
     */
    private function ordenarPorMontoEnSoles(Builder $consulta, array $tasas, string $direccion): void
    {
        $casos = '';
        $valores = [];

        foreach (array_filter($tasas, fn (?string $t): bool => $t !== null) as $monedaId => $tasa) {
            $casos .= ' WHEN ? THEN oportunidades.monto_estimado * CAST(? AS numeric)';
            array_push($valores, $monedaId, $tasa);
        }

        // Sin monedas (un lugar vacío), un NULL con tipo: PostgreSQL rechaza «ORDER BY NULL» como constante.
        $expresion = $casos === '' ? 'CAST(NULL AS numeric)' : "CASE oportunidades.moneda_id{$casos} ELSE NULL END";
        $consulta->orderByRaw("{$expresion} {$direccion} NULLS LAST", $valores);
    }

    /**
     * El motivo con que se perdió cada una (el último paso a «perdida» del historial), con su detalle.
     *
     * @param  list<int>  $oportunidadIds
     * @return array<int, string>
     */
    private function motivosDePerdida(array $oportunidadIds): array
    {
        $motivos = [];
        $tipo = (new Oportunidad)->getMorphClass();
        $filas = HistorialEstado::query()
            ->where('documentable_type', $tipo)
            ->whereIn('documentable_id', $oportunidadIds)
            ->whereIn('hacia_estado_id', Estado::query()->select('id')->where('documento_type', $tipo)->where('codigo', 'perdida'))
            ->with('motivo:id,nombre')
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $partes = array_filter([$fila->motivo_id === null ? null : $fila->motivo->nombre, $fila->motivo_detalle], fn (?string $p): bool => $p !== null && $p !== '');
            $motivos[$fila->documentable_id] = $partes === [] ? '—' : implode(' · ', $partes);
        }

        return $motivos;
    }

    /**
     * El competidor que ganó cada una, con su precio o «precio desconocido».
     *
     * @param  list<int>  $oportunidadIds
     * @return array<int, array{nombre: string, precio: ?string, simbolo: ?string, desconocido: bool}>
     */
    private function ganadoresDe(array $oportunidadIds): array
    {
        $ganadores = OportunidadCompetidor::query()->whereIn('oportunidad_id', $oportunidadIds)->where('es_ganador', true)
            ->with('party:id,razon_social')->get();
        $simbolos = Moneda::query()->whereKey($ganadores->pluck('moneda_id')->filter()->all())->pluck('simbolo', 'id');
        $porOportunidad = [];

        foreach ($ganadores as $ganador) {
            $porOportunidad[$ganador->oportunidad_id] = [
                'nombre' => $ganador->party->razon_social,
                'precio' => $ganador->precio === null ? null : (string) $ganador->precio,
                'simbolo' => $ganador->moneda_id === null ? null : (string) $simbolos[$ganador->moneda_id],
                'desconocido' => $ganador->precio_desconocido,
            ];
        }

        return $porOportunidad;
    }

    /**
     * C13 (plan 2.22, revisión de arquitectura): bloquea la fila de la oportunidad para quien crea o cambia un documento
     * que depende de ella (la cotización al crearse, aceptarse o anular su aceptación), como pide `codigo.md`. Dentro de
     * una transacción. Así no se cruza con ganar, perder ni anular.
     */
    public function bloquearParaDependiente(int $oportunidadId): ResumenOportunidad
    {
        $this->bloquear($oportunidadId, null);

        return $this->resumen($oportunidadId);
    }

    private function bloquear(int $oportunidadId, ?int $versionLeida): Oportunidad
    {
        $oportunidad = Oportunidad::query()->with('estado')->lockForUpdate()->findOrFail($oportunidadId);

        if ($versionLeida !== null && $oportunidad->version !== $versionLeida) {
            throw VersionDesactualizada::de('la oportunidad');
        }

        return $oportunidad;
    }

    /** Bloqueada, abierta y con permiso sobre ella (D10: todas, o las propias si es su ejecutivo). */
    private function bloquearAbiertaPropia(int $oportunidadId, ?int $versionLeida, User $quien): Oportunidad
    {
        $oportunidad = $this->bloquear($oportunidadId, $versionLeida);

        if (! $this->puedeEditarla($quien, $oportunidad->ejecutivo_user_id)) {
            throw OportunidadRechazada::sinPermisoParaEditar();
        }

        $this->exigirAbierta($oportunidad);

        return $oportunidad;
    }

    /** D10: editar todas, o las propias si es su ejecutivo (lo usan `bloquearAbiertaPropia` y `rechazoAlMoverla`). */
    private function puedeEditarla(User $quien, int $ejecutivoUserId): bool
    {
        return app(SeguridadService::class)->puedeSobrePropio($quien, SobreLoPropio::EDITAR, SobreLoPropio::EDITAR_PROPIAS, $ejecutivoUserId);
    }

    private function exigirAbierta(Oportunidad $oportunidad): void
    {
        if (EtapaAbierta::tryFrom($oportunidad->estado->codigo) === null) {
            throw OportunidadRechazada::cerrada($oportunidad->numero);
        }
    }

    /** Activo y con permiso de atender cuentas: una sola definición de «quién vende» (D5). */
    private function validarEjecutivo(int $userId): void
    {
        $usuario = User::query()->find($userId);

        if ($usuario === null || ! $usuario->es_activo || $usuario->es_sistema || ! $usuario->can(CuentasService::PERMISO_ATENDER)) {
            throw OportunidadRechazada::ejecutivoNoDisponible();
        }
    }

    private function validarMonto(string $monto): void
    {
        if (bccomp($monto, '0', 2) <= 0) {
            throw OportunidadRechazada::montoNoPositivo();
        }
    }

    /** El decisor es un contacto de la ficha de la cuenta (D9). */
    private function validarDecisor(int $partyId, ?int $contactoId): void
    {
        if ($contactoId !== null && PartyContacto::query()->whereKey($contactoId)->value('party_id') !== $partyId) {
            throw OportunidadRechazada::decisorDeOtraCuenta();
        }
    }

    private function validarGanador(GanadorConocido $respuesta, ?int $partyId, ?string $precio, ?int $monedaId, bool $desconocido): void
    {
        if ($respuesta !== GanadorConocido::Si) {
            if ($partyId !== null || $precio !== null || $monedaId !== null || $desconocido) {
                throw OportunidadRechazada::ganadorSinRespuesta();
            }

            return;
        }

        match (true) {
            $partyId === null => throw OportunidadRechazada::faltaGanador(),
            $desconocido && ($precio !== null || $monedaId !== null) => throw OportunidadRechazada::precioDesconocidoConPrecio(),
            ! $desconocido && ($precio === null || $monedaId === null) => throw OportunidadRechazada::faltaPrecio(),
            ! $desconocido && bccomp((string) $precio, '0', 2) <= 0 => throw OportunidadRechazada::precioIncompleto(),
            default => null,
        };
    }

    /**
     * Guarda el competidor y le da a su ficha el tipo «Competidor» si no lo tiene (D4). Una ficha inactiva no se usa.
     * Si dos lo asignan a la vez, el segundo ve el rechazo por cruce de periodos: si la ficha ya tiene el tipo, sigue.
     */
    private function guardarCompetidorConRol(OportunidadCompetidor $competidor): void
    {
        if (Party::query()->whereKey($competidor->party_id)->value('es_activo') !== true) {
            throw OportunidadRechazada::fichaInactiva();
        }

        $parties = app(PartiesService::class);
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        if (! $this->esCompetidor($competidor->party_id, $hoy)) {
            try {
                $parties->asignarRol($competidor->party_id, self::ROL_COMPETIDOR, $hoy);
            } catch (PartyRechazada $e) {
                if (! $this->esCompetidor($competidor->party_id, $hoy)) {
                    throw $e;
                }
            }
        }

        $competidor->version = $competidor->exists ? $competidor->version + 1 : 1;
        $competidor->save();
    }

    /**
     * Se pregunta a la base cada vez: después de un rechazo por cruce, la respuesta puede haber cambiado.
     *
     * @phpstan-impure
     */
    private function esCompetidor(int $partyId, CarbonImmutable $dia): bool
    {
        return app(PartiesService::class)->tieneRol($partyId, self::ROL_COMPETIDOR, $dia);
    }

    private function partyDeLaCuenta(Oportunidad $oportunidad): int
    {
        return app(CuentasService::class)->resumen($oportunidad->cuenta_id)->partyId;
    }

    private function guardarConNuevaVersion(Oportunidad $oportunidad): void
    {
        if ($oportunidad->isDirty()) {
            $oportunidad->version++;
            $oportunidad->save();
        }
    }

    private static function texto(?string $texto): ?string
    {
        $texto = $texto === null ? null : trim($texto);

        return $texto === '' ? null : $texto;
    }

    /**
     * Sin origen propio, el detalle es el heredado; uno escrito sin elegir el origen «Otro» se rechaza (plan 2.0, D5).
     *
     * @throws DetalleDeOtroRechazado
     */
    private static function sinDetallePropio(DatosOportunidad $datos, ?string $heredado): ?string
    {
        return trim((string) $datos->origenDetalle) === '' ? $heredado : throw DetalleDeOtroRechazado::sobra('el origen');
    }
}
