<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Enums\CasoRuc;
use App\Modules\Crm\Cuentas\Enums\MotivoNoUsable;
use App\Modules\Crm\Cuentas\Enums\OrigenMarcaCliente;
use App\Modules\Crm\Cuentas\Events\EjecutivoDeCuentaCambiado;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\CoincidenciaNombre;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Parties\ResumenParty;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\NotificacionesService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Lo que Cuentas ofrece a los demás módulos (plan 2.20, D12): siempre un ResumenCuenta, nunca el modelo. Lo que
 * escriben otros módulos es solo marcar y revertir cliente (D8); lo demás son acciones registradas de Cuentas.
 */
final class CuentasService
{
    /**
     * Poder ser el ejecutivo de una cuenta (plan 2.20, D7). Es parte de lo que Cuentas expone: Oportunidades lo usa para
     * su propia lista de vendedores (una sola definición de «quién vende»).
     */
    public const string PERMISO_ATENDER = 'crm.cuentas.atender';

    /** El tipo de la ficha que hace pública a una cuenta (D3). */
    public const string ROL_ENTIDAD = 'entidad';

    /** El tipo que recibe toda ficha con cuenta, también la de un prospecto (D1). */
    public const string ROL_CLIENTE = 'cliente';

    public const string PERMISO_EDITAR = 'crm.cuentas.editar';

    public const string PERMISO_EDITAR_PROPIAS = 'crm.cuentas.editar-propias';

    public const int LIMITE_MAXIMO = 50;

    public function __construct(private readonly PartiesService $parties) {}

    /**
     * El primer paso de «Nueva cuenta» (D2): valida el RUC con su dígito verificador y dice en cuál de los cuatro
     * casos está. No escribe nada.
     *
     * @throws DocumentoIdentidadInvalido|PartyRechazada
     */
    public function revisarRuc(string $ruc): RevisionRuc
    {
        $documento = $this->parties->documento(Catalogo::RUC, $ruc);
        $ficha = $this->parties->buscarPorDocumento(Catalogo::RUC, $documento->numero);
        $cuenta = $ficha === null ? null : $this->deParty($ficha->id);
        $caso = match (true) {
            $cuenta !== null => CasoRuc::CuentaExiste,
            $ficha === null => CasoRuc::FichaNueva,
            ! $ficha->activa => CasoRuc::FichaInactiva,
            default => CasoRuc::FichaSinCuenta,
        };

        return new RevisionRuc($caso, $documento->numero, $documento->tipoPersona() === TipoPersona::Natural, $ficha, $cuenta);
    }

    /**
     * El aviso por nombre igual o parecido de una ficha nueva (D2): nunca bloquea.
     *
     * @return list<CoincidenciaNombre>
     */
    public function parecidosPorNombre(string $nombre, int $limite = 10): array
    {
        return trim($nombre) === '' ? [] : $this->parties->posiblesPorNombre($nombre, $limite);
    }

    /**
     * Crea la cuenta en una sola transacción (D2): si algo falla, no queda nada a medias. Si la ficha no existe, la
     * registra con el tipo «Cliente»; si existe, le asigna ese tipo salvo que ya lo tenga (D1). Si se marca pública,
     * también «Entidad pública» (D3). Las dos carreras (dos personas con el mismo RUC a la vez) terminan en el mensaje
     * de «ya existe».
     *
     * @throws CuentaRechazada|DocumentoIdentidadInvalido|PartyRechazada
     */
    public function crear(DatosCuenta $datos): CuentaCreada
    {
        $revision = $this->revisarRuc($datos->ruc);
        $this->validarEjecutivo($datos->ejecutivoUserId);
        // Plan 2.0, D5: con el origen «Otro», cuál es.
        $origenDetalle = app(CatalogosService::class)->detalleDeOtro(Catalogo::OrigenesComerciales, $datos->origenComercialId, $datos->origenDetalle, 'el origen');

        return DB::transaction(function () use ($datos, $revision, $origenDetalle): CuentaCreada {
            $pareja = null;

            switch ($revision->caso) {
                case CasoRuc::CuentaExiste:
                    throw CuentaRechazada::yaExiste($revision->cuenta ?? throw new LogicException('Sin cuenta.'));
                case CasoRuc::FichaInactiva:
                    throw CuentaRechazada::fichaInactiva();
                case CasoRuc::FichaSinCuenta:
                    $partyId = ($revision->ficha ?? throw new LogicException('Sin ficha.'))->id;
                    $this->bloquearFichaSinCuenta($partyId);
                    $esPublica = $datos->esPublica || $this->parties->tieneRol($partyId, self::ROL_ENTIDAD);
                    $this->validarNivel($esPublica, $datos->nivelGobiernoId);
                    $this->asegurarRol($partyId, self::ROL_CLIENTE);

                    if ($esPublica) {
                        $this->asegurarRol($partyId, self::ROL_ENTIDAD);
                    }

                    break;
                case CasoRuc::FichaNueva:
                    $this->validarNivel($datos->esPublica, $datos->nivelGobiernoId);
                    [$partyId, $pareja] = $this->registrarFicha($revision->ruc, $datos);

                    break;
            }

            try {
                // Su propio punto de guardado: PostgreSQL invalida la transacción ante el error de unicidad.
                $cuenta = DB::transaction(fn (): Cuenta => Cuenta::query()->create([
                    'party_id' => $partyId,
                    'ejecutivo_user_id' => $datos->ejecutivoUserId,
                    'origen_comercial_id' => $datos->origenComercialId,
                    'origen_detalle' => $origenDetalle,
                    'nivel_gobierno_id' => $datos->nivelGobiernoId,
                    'notas' => self::texto($datos->notas),
                    'es_activa' => true,
                ]));
            } catch (UniqueConstraintViolationException) {
                // Carrera del caso 2: otra persona creó la cuenta de esta ficha entretanto.
                throw CuentaRechazada::yaExiste($this->deParty($partyId) ?? throw new LogicException('Sin cuenta.'));
            }

            return new CuentaCreada($this->resumen($cuenta->id), $revision->caso === CasoRuc::FichaNueva, $pareja);
        });
    }

    /**
     * Para los selectores de otros módulos: solo cuentas usables (D9), cuya razón social o nombre comercial contiene
     * el texto, o cuyo RUC empieza con él, sin tildes ni mayúsculas. Primero la de RUC exacto. Menos de dos caracteres
     * no busca. Con `incluirId`, esa cuenta va siempre, aunque no sea usable y no coincida, marcada con su motivo: es
     * el valor que el registro ya tiene (AGENTS.md); nunca se ofrece para trabajo nuevo.
     *
     * @return list<ResumenCuenta>
     */
    public function buscar(string $texto, int $limite = 20, ?int $incluirId = null): array
    {
        $texto = trim($texto);
        $encontradas = [];

        if (mb_strlen($texto) >= 2) {
            $numero = DocumentoIdentidad::normalizar($texto);
            $encontradas = $this->usables(Cuenta::query())
                ->whereIn('cuentas.party_id', Party::query()->select('id')->buscarNombreODocumento($texto))
                ->join('parties', 'parties.id', '=', 'cuentas.party_id')
                ->select('cuentas.*')
                ->orderByRaw('CASE WHEN parties.numero_documento = ? THEN 0 ELSE 1 END', [$numero])
                ->orderBy('parties.razon_social')
                ->orderBy('cuentas.id')
                ->limit(max(1, min($limite, self::LIMITE_MAXIMO)))
                ->get()
                ->all();
        }

        if ($incluirId !== null && ! in_array($incluirId, array_map(fn (Cuenta $c): int => $c->id, $encontradas), true)) {
            $incluida = Cuenta::query()->find($incluirId);

            if ($incluida !== null) {
                $encontradas[] = $incluida;
            }
        }

        return $this->resumenes(new Collection($encontradas), CarbonImmutable::parse(Calendario::hoy()));
    }

    /**
     * Para otro módulo que cuelga trabajo nuevo de la cuenta (plan 2.21, D6), dentro de **su** transacción: bloquea la
     * fila de la cuenta y falla si no es usable. Compartido (`FOR SHARE`) al crear: varias creaciones no se esperan
     * entre sí, pero una desactivación (que bloquea en exclusivo antes de mirar los impedimentos) espera a que
     * terminen. Exclusivo (`FOR UPDATE`) cuando la misma transacción va a cambiar la cuenta (ganar marca al cliente):
     * pedir primero compartido y después exclusivo trabaría a dos que ganan a la vez. Orden de bloqueo de la C3: la
     * cuenta va al final.
     *
     * @throws CuentaRechazada
     */
    public function exigirUsable(int $cuentaId, bool $exclusivo = false): ResumenCuenta
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('exigirUsable bloquea la cuenta: se llama dentro de la transacción de quien la usa.');
        }

        $consulta = Cuenta::query()->whereKey($cuentaId);
        $cuenta = ($exclusivo ? $consulta->lockForUpdate() : $consulta->sharedLock())->first() ?? throw CuentaRechazada::noExiste();
        $motivo = $this->motivoNoUsableDe($cuenta);

        if ($motivo !== null) {
            throw CuentaRechazada::noUsable($motivo);
        }

        return $this->resumenes(new Collection([$cuenta]), CarbonImmutable::parse(Calendario::hoy()))[0];
    }

    /**
     * Varias cuentas en lote (plan 2.21): para una lista o un tablero de otro módulo, sin una consulta por fila.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, ResumenCuenta> por id de cuenta
     */
    public function resumenesDe(array $cuentaIds): array
    {
        $cuentas = Cuenta::query()->whereKey(array_values(array_unique($cuentaIds)))->get();
        $resumenes = $this->resumenes($cuentas, CarbonImmutable::parse(Calendario::hoy()));

        return array_combine(array_map(fn (ResumenCuenta $r): int => $r->id, $resumenes), $resumenes);
    }

    /**
     * La cuenta con «pública» a la fecha dada (hoy si no se da, D3): los tableros preguntan con la fecha de la
     * oportunidad, y cerrar el tipo después no reclasifica la historia.
     */
    public function resumen(int $cuentaId, ?CarbonImmutable $fecha = null): ResumenCuenta
    {
        $cuenta = Cuenta::query()->findOrFail($cuentaId);

        return $this->resumenes(new Collection([$cuenta]), $fecha ?? CarbonImmutable::parse(Calendario::hoy()))[0];
    }

    /**
     * La cuenta de una ficha aunque no sea usable, con su motivo; nula si la ficha no tiene cuenta. Así quien la busca
     * dice «tiene una cuenta inactiva; reactívala» en vez de ofrecer crear otra, que fallaría (una por ficha, D1).
     */
    public function deParty(int $partyId): ?ResumenCuenta
    {
        $cuenta = Cuenta::query()->where('party_id', $partyId)->first();

        return $cuenta === null ? null : $this->resumenes(new Collection([$cuenta]), CarbonImmutable::parse(Calendario::hoy()))[0];
    }

    /**
     * Cambia origen, nivel de gobierno y notas (D7 y D9). Nunca el ejecutivo (reasignar), la fecha de cliente (D8) ni
     * si es pública (se cambia en los tipos de la ficha, D3). Con `crm.cuentas.editar`, cualquiera; con
     * `crm.cuentas.editar-propias`, solo las que atiende. En una privada, el nivel guardado se conserva (D4).
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function editar(int $cuentaId, int $versionLeida, int $origenComercialId, ?int $nivelGobiernoId, ?string $notas, User $quien, ?string $origenDetalle = null): ResumenCuenta
    {
        // Plan 2.0, D5: con «Otro», cuál es.
        $origenDetalle = app(CatalogosService::class)->detalleDeOtro(Catalogo::OrigenesComerciales, $origenComercialId, $origenDetalle, 'el origen');

        DB::transaction(function () use ($cuentaId, $versionLeida, $origenComercialId, $origenDetalle, $nivelGobiernoId, $notas, $quien): void {
            $cuenta = $this->bloquear($cuentaId, $versionLeida);

            if (! app(SeguridadService::class)->puedeSobrePropio($quien, self::PERMISO_EDITAR, self::PERMISO_EDITAR_PROPIAS, $cuenta->ejecutivo_user_id)) {
                throw CuentaRechazada::sinPermisoParaEditar();
            }

            if (! $cuenta->es_activa) {
                throw CuentaRechazada::inactivaNoSeEdita();
            }

            if ($this->parties->tieneRol($cuenta->party_id, self::ROL_ENTIDAD)) {
                $this->validarNivel(true, $nivelGobiernoId);
                $cuenta->nivel_gobierno_id = $nivelGobiernoId;
            }

            $cuenta->origen_comercial_id = $origenComercialId;
            $cuenta->origen_detalle = $origenDetalle;
            $cuenta->notas = self::texto($notas);
            $this->guardarConNuevaVersion($cuenta);
        });

        return $this->resumen($cuentaId);
    }

    /**
     * Pasa una o varias cuentas a otro ejecutivo (D7), todo o nada. Con una sola desde su ficha, compara la versión
     * leída; con varias, no (solo cambia el ejecutivo). Las que ya son suyas no cambian. Cada cambio queda en la
     * bitácora y dispara EjecutivoDeCuentaCambiado; el nuevo ejecutivo recibe un solo aviso.
     *
     * @param  list<int>  $cuentaIds
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function reasignar(array $cuentaIds, int $ejecutivoUserId, bool $incluirOportunidadesAbiertas, ?int $versionLeida = null): int
    {
        $cuentaIds = array_values(array_unique($cuentaIds));

        if ($versionLeida !== null && count($cuentaIds) !== 1) {
            throw CuentaRechazada::versionSoloParaUna();
        }

        $nuevo = $this->validarEjecutivo($ejecutivoUserId);

        $cambiadas = DB::transaction(function () use ($cuentaIds, $ejecutivoUserId, $incluirOportunidadesAbiertas, $versionLeida): array {
            // En orden de id: dos reasignaciones cruzadas no se bloquean entre sí.
            $cuentas = Cuenta::query()->whereKey($cuentaIds)->orderBy('id')->lockForUpdate()->get();

            if ($cuentas->count() !== count($cuentaIds)) {
                throw CuentaRechazada::noExiste();
            }

            $cambiadas = [];

            foreach ($cuentas as $cuenta) {
                if ($versionLeida !== null && $cuenta->version !== $versionLeida) {
                    throw VersionDesactualizada::de('la cuenta');
                }

                if ($cuenta->ejecutivo_user_id === $ejecutivoUserId) {
                    continue;
                }

                $anterior = $cuenta->ejecutivo_user_id;
                $cuenta->ejecutivo_user_id = $ejecutivoUserId;
                $this->guardarConNuevaVersion($cuenta);
                EjecutivoDeCuentaCambiado::dispatch($cuenta->id, $anterior, $ejecutivoUserId, $incluirOportunidadesAbiertas);
                $cambiadas[] = $cuenta->id;
            }

            return $cambiadas;
        });

        if ($cambiadas !== []) {
            $this->avisarAsignadas($nuevo, $cambiadas);
        }

        return count($cambiadas);
    }

    /**
     * Desactiva la cuenta con su motivo (D9): nunca se borra. No se desactiva con trabajo en curso (lo dicen los
     * aportes de ActividadDeCuentas). Con `desactivarFicha` («creada por error»), desactiva también la ficha si solo es
     * «Cliente» y la persona tiene el permiso del núcleo; si no, solo avisa.
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function desactivar(int $cuentaId, int $versionLeida, int $motivoId, ?string $detalle, bool $desactivarFicha, User $quien): CuentaDesactivada
    {
        [$fichaDesactivada, $aviso] = DB::transaction(function () use ($cuentaId, $versionLeida, $motivoId, $detalle, $desactivarFicha, $quien): array {
            $cuenta = $this->bloquear($cuentaId, $versionLeida);

            if (! $cuenta->es_activa) {
                throw CuentaRechazada::yaEstaInactiva();
            }

            $detalle = $this->validarMotivo($motivoId, $detalle);

            // D9.2: solo una creada por error se lleva también su ficha.
            if ($desactivarFicha && Motivo::query()->whereKey($motivoId)->value('codigo') !== CuentasServiceProvider::MOTIVO_CREADA_POR_ERROR) {
                throw CuentaRechazada::fichaSoloPorError();
            }
            $impedimentos = app(ActividadDeCuentas::class)->impedimentosParaDesactivar($cuenta->id);

            if ($impedimentos !== []) {
                throw CuentaRechazada::conTrabajoEnCurso($impedimentos);
            }

            $cuenta->es_activa = false;
            $cuenta->motivo_inactiva_id = $motivoId;
            $cuenta->motivo_inactiva_detalle = $detalle;
            $this->guardarConNuevaVersion($cuenta);

            return $desactivarFicha ? $this->desactivarFichaCreadaPorError($cuenta->party_id, $quien) : [false, null];
        });

        return new CuentaDesactivada($this->resumen($cuentaId), $fichaDesactivada, $aviso);
    }

    /**
     * Reactiva la cuenta (D9): exige su ficha activa. Limpia el motivo; la bitácora conserva lo que fue.
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function reactivar(int $cuentaId, int $versionLeida): ResumenCuenta
    {
        DB::transaction(function () use ($cuentaId, $versionLeida): void {
            $cuenta = $this->bloquear($cuentaId, $versionLeida);

            if ($cuenta->es_activa) {
                throw CuentaRechazada::yaEstaActiva();
            }

            // Bloqueada: si alguien la desactiva a la vez, uno espera al otro y no queda una cuenta activa sin ficha.
            if (Party::query()->whereKey($cuenta->party_id)->lockForUpdate()->value('es_activo') !== true) {
                throw CuentaRechazada::fichaInactiva();
            }

            $cuenta->es_activa = true;
            $cuenta->motivo_inactiva_id = null;
            $cuenta->motivo_inactiva_detalle = null;
            $this->guardarConNuevaVersion($cuenta);
        });

        return $this->resumen($cuentaId);
    }

    /**
     * Para el efecto «ganar» de Oportunidades (D8.3): la cuenta es cliente por esa oportunidad. Repetirla no la
     * duplica. `cliente_desde` queda en la fecha más antigua de sus marcas: una ganada cargada después con cierre
     * anterior corrige «nuevos clientes por mes». Es un efecto del sistema: no compara versiones.
     */
    public function marcarCliente(int $cuentaId, CarbonImmutable $fecha, int $oportunidadId): void
    {
        DB::transaction(function () use ($cuentaId, $fecha, $oportunidadId): void {
            $cuenta = Cuenta::query()->lockForUpdate()->find($cuentaId) ?? throw CuentaRechazada::noExiste();

            if (! $cuenta->marcasCliente()->where('oportunidad_id', $oportunidadId)->exists()) {
                $cuenta->marcasCliente()->create(['origen' => OrigenMarcaCliente::Oportunidad, 'oportunidad_id' => $oportunidadId, 'fecha' => $fecha->toDateString()]);
            }

            $this->recalcularClienteDesde($cuenta);
        });
    }

    /**
     * Al anular el «ganar» de esa oportunidad (D8.3, regla 6): quita su marca, y la cuenta queda cliente por otra
     * marca o vuelve a prospecto.
     */
    public function revertirCliente(int $cuentaId, int $oportunidadId): void
    {
        DB::transaction(function () use ($cuentaId, $oportunidadId): void {
            $cuenta = Cuenta::query()->lockForUpdate()->find($cuentaId) ?? throw CuentaRechazada::noExiste();
            $cuenta->marcasCliente()->where('oportunidad_id', $oportunidadId)->first()?->delete();
            $this->recalcularClienteDesde($cuenta);
        });
    }

    /**
     * Cliente de antes del sistema (D8.5): fecha no futura y comentario obligatorio. Una sola marca manual vigente.
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function marcarClienteManual(int $cuentaId, int $versionLeida, CarbonImmutable $fecha, ?string $comentario): ResumenCuenta
    {
        DB::transaction(function () use ($cuentaId, $versionLeida, $fecha, $comentario): void {
            $cuenta = $this->bloquear($cuentaId, $versionLeida);
            $comentario = self::texto($comentario) ?? throw CuentaRechazada::faltaComentario();

            if (! $cuenta->es_activa) {
                throw CuentaRechazada::inactivaNoSeEdita();
            }

            if ($fecha->toDateString() > Calendario::hoy()) {
                throw CuentaRechazada::fechaClienteFutura();
            }

            if ($cuenta->marcasCliente()->vigentes()->where('origen', OrigenMarcaCliente::Manual)->exists()) {
                throw CuentaRechazada::yaTieneMarcaManual();
            }

            $cuenta->marcasCliente()->create(['origen' => OrigenMarcaCliente::Manual, 'fecha' => $fecha->toDateString(), 'comentario' => $comentario]);
            $this->recalcularClienteDesde($cuenta);
        });

        return $this->resumen($cuentaId);
    }

    /**
     * Corrige una marca manual puesta por error (D8.6): solo si la cuenta no es cliente por oportunidades ganadas. La
     * marca se anula con su motivo (no se borra) y la cuenta vuelve a prospecto.
     *
     * @throws CuentaRechazada|VersionDesactualizada
     */
    public function quitarMarcaCliente(int $cuentaId, int $versionLeida, ?string $motivo, User $quien): ResumenCuenta
    {
        DB::transaction(function () use ($cuentaId, $versionLeida, $motivo, $quien): void {
            $cuenta = $this->bloquear($cuentaId, $versionLeida);
            $minimo = app(ParametrosService::class)->entero('motivos.minimo_detalle_otro');

            // Como al marcarla: una cuenta inactiva no se toca hasta reactivarla.
            if (! $cuenta->es_activa) {
                throw CuentaRechazada::inactivaNoSeEdita();
            }
            $motivo = self::texto($motivo);

            if (mb_strlen((string) $motivo) < $minimo) {
                throw CuentaRechazada::motivoQuitarCorto($minimo);
            }

            if ($cuenta->marcasCliente()->where('origen', OrigenMarcaCliente::Oportunidad)->exists()) {
                throw CuentaRechazada::tieneGanadas();
            }

            $manual = $cuenta->marcasCliente()->vigentes()->where('origen', OrigenMarcaCliente::Manual)->first() ?? throw CuentaRechazada::sinMarcaManual();
            $manual->fill(['quitada_en' => Calendario::ahora(), 'quitada_por_user_id' => $quien->id, 'motivo_quitada' => $motivo])->save();
            $this->recalcularClienteDesde($cuenta);
        });

        return $this->resumen($cuentaId);
    }

    /**
     * Las cuentas usables cuyo ejecutivo ya no está activo (D7): las de la alerta y el aviso de la portada.
     *
     * @param  Builder<Cuenta>  $consulta
     * @return Builder<Cuenta>
     */
    public function conEjecutivoInactivo(Builder $consulta): Builder
    {
        return $this->usables($consulta)->whereIn('cuentas.ejecutivo_user_id', User::query()->select('id')->where('es_activo', false));
    }

    /** Por qué no es usable (D9), con la ficha ya cargada; nulo si lo es. Manda la cuenta si las dos están inactivas. */
    public function motivoNoUsableDe(Cuenta $cuenta): ?MotivoNoUsable
    {
        return match (true) {
            ! $cuenta->es_activa => MotivoNoUsable::CuentaInactiva,
            ! $cuenta->party->es_activo => MotivoNoUsable::FichaInactiva,
            default => null,
        };
    }

    /**
     * Solo las usables: activas y con su ficha activa (D9).
     *
     * @param  Builder<Cuenta>  $consulta
     * @return Builder<Cuenta>
     */
    public function usables(Builder $consulta): Builder
    {
        return $consulta->where('cuentas.es_activa', true)->whereIn('cuentas.party_id', Party::query()->select('id')->where('es_activo', true));
    }

    /**
     * @return array{0: int, 1: ?ResumenParty} la ficha nueva y su posible pareja DNI ↔ RUC 10
     */
    private function registrarFicha(string $ruc, DatosCuenta $datos): array
    {
        $roles = $datos->esPublica ? [self::ROL_CLIENTE, self::ROL_ENTIDAD] : [self::ROL_CLIENTE];

        try {
            $registrada = $this->parties->registrar(new DatosParty(
                Catalogo::RUC,
                $ruc,
                razonSocial: $datos->razonSocial,
                nombreComercial: $datos->nombreComercial,
                nombres: $datos->nombres,
                apellidos: $datos->apellidos,
                rubroId: $datos->rubroId,
            ), $roles);
        } catch (PartyRechazada $rechazo) {
            // Carrera del caso 4: otra persona registró la ficha entretanto (con su cuenta, en la misma transacción).
            $ficha = $this->parties->buscarPorDocumento(Catalogo::RUC, $ruc);

            if ($ficha === null) {
                throw $rechazo;
            }

            throw ($cuenta = $this->deParty($ficha->id)) !== null ? CuentaRechazada::yaExiste($cuenta) : CuentaRechazada::fichaRegistradaRecien();
        }

        return [$registrada->id, $registrada->pareja];
    }

    /**
     * Carrera del caso 2: sin este bloqueo, las dos personas verían la ficha sin «Cliente» y la segunda chocaría al
     * asignarlo («el periodo se cruza»), no en la cuenta. Bloquea la fila de la ficha (solo lectura: no la escribe)
     * y vuelve a mirar con lo que la otra ya confirmó.
     */
    private function bloquearFichaSinCuenta(int $partyId): void
    {
        $activa = Party::query()->whereKey($partyId)->lockForUpdate()->value('es_activo');
        $cuenta = $this->deParty($partyId);

        if ($cuenta !== null) {
            throw CuentaRechazada::yaExiste($cuenta);
        }

        if ($activa !== true) {
            throw CuentaRechazada::fichaInactiva();
        }
    }

    private function bloquear(int $cuentaId, int $versionLeida): Cuenta
    {
        $cuenta = Cuenta::query()->lockForUpdate()->find($cuentaId) ?? throw CuentaRechazada::noExiste();

        if ($cuenta->version !== $versionLeida) {
            throw VersionDesactualizada::de('la cuenta');
        }

        return $cuenta;
    }

    /** D8: `cliente_desde` es siempre la fecha más antigua de las marcas vigentes, o nula sin ninguna. */
    private function recalcularClienteDesde(Cuenta $cuenta): void
    {
        $masAntigua = $cuenta->marcasCliente()->vigentes()->min('fecha');
        $cuenta->cliente_desde = $masAntigua === null ? null : CarbonImmutable::parse($masAntigua);
        $this->guardarConNuevaVersion($cuenta);
    }

    private function guardarConNuevaVersion(Cuenta $cuenta): void
    {
        if ($cuenta->isDirty()) {
            $cuenta->version++;
            $cuenta->save();
        }
    }

    /**
     * Un motivo activo de la lista de desactivar; detalle solo con «Otro», y entonces con el mínimo del parámetro.
     *
     * @return ?string el detalle limpio
     */
    private function validarMotivo(int $motivoId, ?string $detalle): ?string
    {
        $motivo = Motivo::query()
            ->whereKey($motivoId)
            ->where('es_activo', true)
            ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR))
            ->first() ?? throw CuentaRechazada::motivoNoValido();
        $detalle = self::texto($detalle);

        if (! $motivo->es_otro) {
            return $detalle === null ? null : throw CuentaRechazada::detalleSobrante();
        }

        $minimo = app(ParametrosService::class)->entero('motivos.minimo_detalle_otro');

        return mb_strlen((string) $detalle) >= $minimo ? $detalle : throw CuentaRechazada::detalleCorto($minimo);
    }

    /**
     * D9, «creada por error»: la ficha se desactiva solo si no tiene otros tipos que «Cliente» y la persona tiene el
     * permiso del núcleo. Si no, se avisa que la ficha queda con datos que no son de esa empresa.
     *
     * @return array{0: bool, 1: ?string}
     */
    private function desactivarFichaCreadaPorError(int $partyId, User $quien): array
    {
        if (! $quien->can('parties.desactivar')) {
            return [false, 'La ficha sigue activa en Personas y empresas, con datos que no son de esa empresa: pide a quien puede desactivar fichas que la revise.'];
        }

        $otrosTipos = PartyRol::query()
            ->where('party_id', $partyId)
            ->vigenteEl(Calendario::hoy())
            ->whereHas('rol', fn (Builder $q): Builder => $q->where('codigo', '<>', self::ROL_CLIENTE))
            ->exists();

        if ($otrosTipos) {
            return [false, 'La ficha sigue activa: tiene otros tipos además de «Cliente». Revísala en Personas y empresas.'];
        }

        $this->parties->desactivar($partyId, (int) Party::query()->whereKey($partyId)->value('version'));

        return [true, null];
    }

    /**
     * Un solo aviso por reasignación (D7): «Se te asignaron N cuentas» o el nombre de la única.
     *
     * @param  list<int>  $cuentaIds
     */
    private function avisarAsignadas(User $ejecutivo, array $cuentaIds): void
    {
        $titulo = count($cuentaIds) === 1
            ? 'Se te asignó la cuenta '.$this->resumen($cuentaIds[0])->etiqueta
            : 'Se te asignaron '.count($cuentaIds).' cuentas';

        DB::afterCommit(fn () => app(NotificacionesService::class)->avisar(
            CuentasServiceProvider::AVISO_ASIGNADAS,
            $ejecutivo,
            $titulo,
            'Ahora las atiendes tú. Revísalas en Cuentas.',
            '/cuentas?ejecutivo='.$ejecutivo->id,
            'asignadas:'.Str::uuid()->toString(),
        ));
    }

    /** Sin fallar si ya lo tiene vigente: asignarRol rechaza un periodo que se cruza (D1). */
    private function asegurarRol(int $partyId, string $codigoRol): void
    {
        if (! $this->parties->tieneRol($partyId, $codigoRol)) {
            $this->parties->asignarRol($partyId, $codigoRol, CarbonImmutable::parse(Calendario::hoy()));
        }
    }

    /** Activo y con permiso de atender cuentas (D7). */
    private function validarEjecutivo(int $userId): User
    {
        $usuario = User::query()->find($userId);

        if ($usuario === null || ! $usuario->es_activo || $usuario->es_sistema || ! $usuario->can(self::PERMISO_ATENDER)) {
            throw CuentaRechazada::ejecutivoNoDisponible();
        }

        return $usuario;
    }

    /** El nivel de gobierno, obligatorio en una pública y ausente en una privada (D4). */
    private function validarNivel(bool $esPublica, ?int $nivelGobiernoId): void
    {
        if ($esPublica && $nivelGobiernoId === null) {
            throw CuentaRechazada::nivelDeGobiernoFalta();
        }

        if (! $esPublica && $nivelGobiernoId !== null) {
            throw CuentaRechazada::nivelDeGobiernoEnPrivada();
        }
    }

    private static function texto(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }

    /**
     * En lote: una consulta para las fichas, ejecutivos y niveles, y otra para saber cuáles son públicas a la fecha.
     *
     * @param  Collection<int, Cuenta>  $cuentas
     * @return list<ResumenCuenta>
     */
    private function resumenes(Collection $cuentas, CarbonImmutable $fecha): array
    {
        $cuentas->load(['party', 'ejecutivo', 'nivelGobierno']);
        $publicas = $cuentas->isEmpty() ? [] : Party::query()
            ->whereIn('id', $cuentas->pluck('party_id')->all())
            ->conRolVigente(self::ROL_ENTIDAD, $fecha->toDateString())
            ->pluck('id')
            ->all();

        return $cuentas->map(function (Cuenta $cuenta) use ($publicas): ResumenCuenta {
            $party = $cuenta->party;
            $motivo = $this->motivoNoUsableDe($cuenta);

            return new ResumenCuenta(
                $cuenta->id,
                $party->id,
                "{$party->razon_social} · {$party->numero_documento}",
                $cuenta->ejecutivo_user_id,
                $cuenta->ejecutivo->name,
                in_array($party->id, $publicas, true),
                $cuenta->nivel_gobierno_id,
                $cuenta->nivelGobierno?->nombre,
                $cuenta->cliente_desde,
                $motivo === null,
                $motivo,
                $cuenta->origen_comercial_id,
                $cuenta->origen_detalle,
            );
        })->values()->all();
    }
}
