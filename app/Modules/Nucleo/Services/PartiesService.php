<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\CampoNombre;
use App\Modules\Nucleo\Parties\CoincidenciaNombre;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Parties\PartyObtenida;
use App\Modules\Nucleo\Parties\PartyRegistrada;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\Parties\ResumenCuentaBancaria;
use App\Modules\Nucleo\Parties\ResumenParty;
use App\Modules\Nucleo\Parties\ResumenSede;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * M1 · La API pública de parties y roles (plan 1.3, D6), y la lectura de sus contactos, sedes y cuentas (plan 1.3b,
 * D7: su escritura va en ContactosPartyService, SedesPartyService y CuentasBancariasService, internos del núcleo). Los demás módulos y los procesos automáticos
 * (convocatorias del OECE, padrón SUNAT) solo pasan por aquí y guardan el id; nunca tocan los modelos.
 *
 * Todo número pasa por DocumentoIdentidad (D4). Tipo y número de documento y tipo de persona no cambian
 * (D5). Las marcas tributarias se cambian solo con editarTributarios() (D7). Un rol es un periodo: `hasta`
 * es el último día vigente y vacío es sin fin, igual que la restricción de la base (D2, D8).
 */
final class PartiesService
{
    /** Lo que se edita en toda party; además, nombres y apellidos (natural) o razón social (jurídica). */
    private const array EDITABLES = ['nombre_comercial', 'direccion_fiscal', 'ubigeo_id', 'telefono', 'email'];

    private const array TRIBUTARIOS = ['es_agente_retencion', 'es_agente_percepcion', 'es_buen_contribuyente'];

    /** Tope de resultados de buscar(): con las entidades del OECE habrá miles (D6). */
    private const int LIMITE_MAXIMO = 50;

    public function __construct(
        private readonly CatalogosService $catalogos,
        private readonly ParametrosService $parametros,
    ) {}

    /**
     * Alta de una persona, con sus roles iniciales desde hoy, todo en una sola operación: si un rol no se
     * puede asignar, no se registra nada. Si el documento ya existe, se rechaza diciendo si está activa o no.
     * Devuelve también la pareja DNI ↔ RUC 10, si existe, para avisar sin bloquear (D9).
     *
     * @param  list<string>  $roles  códigos de rol
     *
     * @throws PartyRechazada|DocumentoIdentidadInvalido
     */
    public function registrar(DatosParty $datos, array $roles = []): PartyRegistrada
    {
        $party = $this->construir($datos);
        $hoy = Calendario::ahora()->startOfDay();

        DB::transaction(function () use ($party, $datos, $roles, $hoy): void {
            try {
                // Su propio punto de guardado: PostgreSQL invalida la transacción ante el error de unicidad.
                DB::transaction(fn (): bool => $party->save());
            } catch (UniqueConstraintViolationException) {
                $existente = $this->buscarPorDocumento($datos->codigoTipoDocumento, $party->numero_documento);

                throw PartyRechazada::duplicada($existente->etiqueta ?? $party->numero_documento, $existente->activa ?? true);
            }

            foreach (array_unique($roles) as $codigo) {
                try {
                    $this->asignarRol($party->id, $codigo, $hoy);
                } catch (PartyRechazada) {
                    throw PartyRechazada::rolNoDisponibleAlRegistrar(RolParty::query()->where('codigo', $codigo)->value('nombre') ?? $codigo);
                }
            }
        });

        return new PartyRegistrada($party->id, $this->posibleMismaPersona($datos->codigoTipoDocumento, $party->numero_documento));
    }

    /**
     * Para procesos automáticos: devuelve la party del documento, o la crea si no existe. Intenta insertar
     * primero y, si la unicidad lo impide, lee la que ya está: así dos procesos simultáneos con el mismo
     * documento reciben el mismo id. Una desactivada vuelve como está, sin reactivarse. No asigna roles ni
     * cambia los datos de una existente.
     *
     * @throws PartyRechazada|DocumentoIdentidadInvalido
     */
    public function obtenerOCrear(DatosParty $datos): PartyObtenida
    {
        $party = $this->construir($datos);

        try {
            // Su propio punto de guardado: PostgreSQL invalida la transacción ante el error de unicidad.
            DB::transaction(fn (): bool => $party->save());

            return new PartyObtenida($party->id, self::etiqueta($party), true, true);
        } catch (UniqueConstraintViolationException) {
            $existente = $this->buscarPorDocumento($datos->codigoTipoDocumento, $party->numero_documento)
                ?? throw PartyRechazada::noExiste();

            return new PartyObtenida($existente->id, $existente->etiqueta, $existente->activa, false);
        }
    }

    /**
     * El documento normalizado y validado según su tipo del catálogo (activo). Lo usa la pantalla de alta para
     * avisar en el primer paso, con las mismas reglas que registrar.
     *
     * @throws PartyRechazada|DocumentoIdentidadInvalido
     */
    public function documento(string $codigoTipoDocumento, string $numero): DocumentoIdentidad
    {
        $tipo = $this->tipoDocumentoActivo($codigoTipoDocumento);

        return DocumentoIdentidad::de($codigoTipoDocumento, $numero, $tipo->longitud, $tipo->nombre);
    }

    /** Por documento, normalizando el número antes. Activa o no. */
    public function buscarPorDocumento(string $codigoTipoDocumento, string $numero): ?ResumenParty
    {
        $party = Party::query()
            ->whereHas('tipoDocumento', fn (Builder $q): Builder => $q->where('codigo', $codigoTipoDocumento))
            ->where('numero_documento', DocumentoIdentidad::normalizar($numero))
            ->first();

        return $party === null ? null : self::resumen($party);
    }

    /**
     * La party con el otro documento de la misma persona (DNI ↔ RUC 10), si existe (D9).
     */
    public function posibleMismaPersona(string $codigoTipoDocumento, string $numero): ?ResumenParty
    {
        try {
            $pareja = DocumentoIdentidad::de($codigoTipoDocumento, $numero)->pareja();
        } catch (DocumentoIdentidadInvalido) {
            return null;
        }

        return $pareja === null ? null : $this->buscarPorDocumento($pareja->codigoTipo, $pareja->numero);
    }

    /**
     * Para los selectores: parties activas cuyo número empieza con el texto (ya normalizado: «20-1234» sirve)
     * o cuya razón social o nombre comercial lo contiene, sin mayúsculas ni acentos (D11). Primero la de
     * número exacto; luego por razón social. Con rol, solo las que lo tienen vigente hoy. Menos de dos
     * caracteres no busca.
     *
     * @return list<ResumenParty>
     */
    public function buscar(string $texto, ?string $codigoRol = null, int $limite = 20): array
    {
        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return [];
        }

        $numero = DocumentoIdentidad::normalizar($texto);
        $hoy = Calendario::hoy();

        $consulta = Party::query()->where('es_activo', true)->buscarNombreODocumento($texto);

        if ($codigoRol !== null) {
            $consulta->conRolVigente($codigoRol, $hoy);
        }

        return $consulta
            ->orderByRaw('CASE WHEN numero_documento = ? THEN 0 ELSE 1 END', [$numero])
            ->orderBy('razon_social')
            ->orderBy('id')
            ->limit(max(1, min($limite, self::LIMITE_MAXIMO)))
            ->get()
            ->map(fn (Party $party): ResumenParty => self::resumen($party))
            ->values()
            ->all();
    }

    /**
     * La etiqueta de una party ya elegida, aunque esté desactivada (los selectores deben mostrar el valor
     * que el registro ya tiene).
     */
    public function etiquetaDe(int $id): ?string
    {
        $party = Party::query()->find($id);

        if ($party === null) {
            return null;
        }

        return self::etiqueta($party).($party->es_activo ? '' : ' (inactiva)');
    }

    /**
     * Cambia los datos generales. En una natural, la razón social se rearma con apellidos y nombres. Los campos
     * del otro tipo de persona pueden llegar vacíos (una acción manda todos sus campos), nunca con valor.
     *
     * @param  array<string, mixed>  $cambios  columnas de EDITABLES, nombres, apellidos y razón social
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function editar(int $id, array $cambios, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $cambios, $versionLeida): void {
            $party = $this->bloquear($id, $versionLeida);
            $this->rechazarAjenos($cambios, [...self::EDITABLES, 'nombres', 'apellidos', 'razon_social']);
            $ajenos = $party->tipo === TipoPersona::Natural ? ['razon_social'] : ['nombres', 'apellidos'];

            foreach ($ajenos as $campo) {
                if (self::texto($cambios[$campo] ?? null) !== null) {
                    throw $party->tipo === TipoPersona::Natural ? PartyRechazada::razonSocialDeNatural() : PartyRechazada::juridicaConNombres();
                }

                unset($cambios[$campo]);
            }

            foreach ($cambios as $campo => $valor) {
                $party->setAttribute($campo, $campo === 'ubigeo_id' ? self::entero($valor) : self::texto($valor));
            }

            $this->completarNombre($party);
            $this->validarContacto($party);
            $this->guardarConNuevaVersion($party);
            $this->sincronizarNombreDeUsuario($party);
        });
    }

    /**
     * Las fichas cuyo nombre se parece al escrito (plan 2.20a, N1), para avisar antes de crear una cuenta del CRM; nunca
     * bloquea. Compara el nombre normalizado contra la razón social y el nombre comercial de cada ficha, activa o no.
     * Cada ficha sale una vez, con su mejor coincidencia: mismo nombre antes que parecido; entre parecidos, el mayor; a
     * igual, la razón social. Primero las de mismo nombre y luego las parecidas, de mayor a menor. Usa los índices de
     * trigramas con el umbral del parámetro `parties.umbral_nombre_parecido`; el mismo nombre normalizado da parecido 1 y
     * siempre pasa (el umbral no puede ser mayor que 1).
     *
     * El umbral se fija con `set_config(..., true)`, que dura hasta el final de la transacción de nivel superior: si se
     * llama dentro de otra transacción, sigue puesto después para todo `%` de esa transacción. Hoy no hace daño (cada
     * llamada lo vuelve a fijar); quien use `%` por su cuenta, que fije el suyo.
     *
     * @return list<CoincidenciaNombre>
     */
    public function posiblesPorNombre(string $nombre, int $limite = 10): array
    {
        $normal = PosiblesDuplicados::normalizar($nombre);

        if ($normal === '') {
            return [];
        }

        $umbral = $this->parametros->decimal('parties.umbral_nombre_parecido');

        $filas = DB::transaction(function () use ($normal, $umbral, $limite): array {
            // Como SET LOCAL, pero con el valor como parámetro: vale solo dentro de esta transacción.
            DB::select("SELECT set_config('pg_trgm.similarity_threshold', ?, true)", [$umbral]);

            return DB::select(<<<'SQL'
                SELECT id, campo, igual, parecido FROM (
                    SELECT DISTINCT ON (id) id, campo, igual, parecido, orden FROM (
                        SELECT id, 'razon_social' AS campo, nombre_normalizado = :n1 AS igual,
                               similarity(nombre_normalizado, :n2) AS parecido, 0 AS orden
                        FROM parties WHERE nombre_normalizado % :n3
                        UNION ALL
                        SELECT id, 'nombre_comercial', nombre_comercial_normalizado = :n5,
                               similarity(nombre_comercial_normalizado, :n6), 1
                        FROM parties WHERE nombre_comercial_normalizado % :n7
                    ) candidatas
                    ORDER BY id, igual DESC, parecido DESC, orden
                ) mejores
                ORDER BY igual DESC, parecido DESC, orden, id
                LIMIT :limite
                SQL, ['n1' => $normal, 'n2' => $normal, 'n3' => $normal, 'n5' => $normal, 'n6' => $normal, 'n7' => $normal, 'limite' => $limite]);
        });

        $parties = Party::query()->whereIn('id', array_map(fn (object $fila): int => (int) $fila->id, $filas))->get()->keyBy('id');

        return array_values(array_map(fn (object $fila): CoincidenciaNombre => new CoincidenciaNombre(
            self::resumen($parties[(int) $fila->id]),
            CampoNombre::from((string) $fila->campo),
            (bool) $fila->igual,
            round((float) $fila->parecido, 2),
        ), $filas));
    }

    /**
     * El rubro de la empresa, una sección de la CIIU (plan 2.20a, N4); null lo quita. Acción propia: `editar`
     * reemplaza todos los datos generales, y quien solo corrige el rubro no debe poder cambiar la razón social.
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function cambiarRubro(int $id, ?int $rubroId, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $rubroId, $versionLeida): void {
            $party = $this->bloquear($id, $versionLeida);
            $party->rubro_id = $rubroId === $party->rubro_id ? $rubroId : $this->rubroDisponible($rubroId);
            $this->guardarConNuevaVersion($party);
        });
    }

    /**
     * Las marcas que deciden retenciones y percepciones (D7). Su permiso lo exige la acción.
     *
     * @param  array<string, mixed>  $cambios
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function editarTributarios(int $id, array $cambios, int $versionLeida): void
    {
        $this->rechazarAjenos($cambios, self::TRIBUTARIOS);

        DB::transaction(function () use ($id, $cambios, $versionLeida): void {
            $party = $this->bloquear($id, $versionLeida);

            foreach ($cambios as $campo => $valor) {
                $party->setAttribute($campo, (bool) $valor);
            }

            $this->guardarConNuevaVersion($party);
        });
    }

    /**
     * Deja de ofrecerse en los selectores; lo ya registrado la conserva.
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function desactivar(int $id, int $versionLeida): void
    {
        $this->cambiarEstado($id, false, $versionLeida);
    }

    /**
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function activar(int $id, int $versionLeida): void
    {
        $this->cambiarEstado($id, true, $versionLeida);
    }

    /**
     * Borra de verdad una party que nunca se usó, con sus propios roles, contactos, sedes y cuentas (D5; plan 1.3b). Si otra tabla la referencia,
     * la llave foránea lo impide, nada se borra y se sugiere desactivarla.
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function borrar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $party = $this->bloquear($id, $versionLeida);

            if (app(EmpresaService::class)->esLaFicha($party)) {
                throw PartyRechazada::esLaFichaDeLaEmpresa('borrarla');
            }

            // En su propio punto de guardado: si la FK lo impide, los roles vuelven también.
            try {
                DB::transaction(function () use ($party): void {
                    // Sus propias partes, por modelo (cada una deja su bitácora). Las sedes antes que los contactos
                    // a los que apuntan (plan 1.3b).
                    $party->roles()->get()->each(fn (PartyRol $periodo): ?bool => $periodo->delete());
                    // Sus redes y las de sus contactos antes que los contactos (plan 1.4o).
                    PartyRedSocial::query()->where('party_id', $party->id)->get()->each(fn (PartyRedSocial $red): ?bool => $red->delete());
                    $party->sedes()->get()->each(fn (PartySede $sede): ?bool => $sede->delete());
                    $party->cuentasBancarias()->get()->each(fn (PartyCuentaBancaria $cuenta): ?bool => $cuenta->delete());
                    $party->contactos()->get()->each(fn (PartyContacto $contacto): ?bool => $contacto->delete());
                    $party->delete();
                });
            } catch (QueryException $e) {
                // 23503: foreign_key_violation.
                throw ($e->errorInfo[0] ?? null) === '23503' ? PartyRechazada::enUso() : $e;
            }
        });
    }

    /**
     * Abre un periodo del rol desde la fecha dada, sin fin. La base rechaza que se cruce con otro del mismo rol.
     *
     * @throws PartyRechazada
     */
    public function asignarRol(int $partyId, string $codigoRol, CarbonImmutable $desde): int
    {
        return DB::transaction(function () use ($partyId, $codigoRol, $desde): int {
            $party = Party::query()->lockForUpdate()->find($partyId) ?? throw PartyRechazada::noExiste();

            if (! $party->es_activo) {
                throw PartyRechazada::inactiva();
            }

            $rol = RolParty::query()->where('codigo', $codigoRol)->where('es_activo', true)->first()
                ?? throw PartyRechazada::rolNoDisponible($codigoRol);

            $periodo = new PartyRol(['party_id' => $party->id, 'rol_party_id' => $rol->id, 'desde' => $desde->toDateString()]);

            try {
                DB::transaction(fn (): bool => $periodo->save());
            } catch (QueryException $e) {
                // 23P01: exclusion_violation (party_roles_sin_cruce).
                throw ($e->errorInfo[0] ?? null) === '23P01' ? PartyRechazada::periodoSeCruza($rol->nombre) : $e;
            }

            return $periodo->id;
        });
    }

    /**
     * Cierra el periodo abierto del rol: `hasta` es su último día vigente.
     *
     * @throws PartyRechazada|VersionDesactualizada
     */
    public function cerrarRol(int $partyId, string $codigoRol, CarbonImmutable $hasta, int $versionLeida): void
    {
        DB::transaction(function () use ($partyId, $codigoRol, $hasta, $versionLeida): void {
            $rol = RolParty::query()->where('codigo', $codigoRol)->first() ?? throw PartyRechazada::rolNoDisponible($codigoRol);

            $periodo = PartyRol::query()
                ->where('party_id', $partyId)
                ->where('rol_party_id', $rol->id)
                ->whereNull('hasta')
                ->lockForUpdate()
                ->first() ?? throw PartyRechazada::sinPeriodoAbierto($rol->nombre);

            if ($periodo->version !== $versionLeida) {
                throw VersionDesactualizada::de("el rol {$rol->nombre}");
            }

            if ($hasta->lt($periodo->desde)) {
                throw PartyRechazada::hastaAntesDeDesde($periodo->desde->format('d/m/Y'));
            }

            $periodo->hasta = $hasta->startOfDay();
            $periodo->version++;
            $periodo->save();
        });
    }

    /**
     * Si la party cumple el rol en la fecha (hoy, en Lima, si no se da). El día `hasta` todavía cuenta, como
     * en la restricción de la base.
     */
    public function tieneRol(int $partyId, string $codigoRol, ?CarbonImmutable $fecha = null): bool
    {
        $dia = $fecha?->toDateString() ?? Calendario::hoy();

        return PartyRol::query()
            ->where('party_id', $partyId)
            ->whereHas('rol', fn (Builder $q): Builder => $q->where('codigo', $codigoRol))
            ->vigenteEl($dia)
            ->exists();
    }

    /**
     * Los contactos vigentes hoy, el principal primero (plan 1.3b, D7). Para elegir el contacto de una cotización
     * o una actividad (CRM).
     *
     * @return list<ResumenContacto>
     */
    public function contactosVigentes(int $partyId): array
    {
        return PartyContacto::query()
            ->where('party_id', $partyId)
            ->vigentes()
            ->orderByDesc('es_principal')->orderBy('apellidos')->orderBy('nombres')
            ->get()
            ->map(fn (PartyContacto $c): ResumenContacto => new ResumenContacto(
                $c->id,
                $c->cargo === null ? $c->nombreCompleto() : "{$c->nombreCompleto()} · {$c->cargo}",
                $c->es_principal,
                $c->es_decisor,
            ))
            ->values()->all();
    }

    /**
     * Si hay contactos vigentes y ninguno es el principal (plan 1.3b, D2): la ficha lo avisa y el CRM lo usará.
     */
    public function faltaContactoPrincipal(int $partyId): bool
    {
        $vigentes = PartyContacto::query()->where('party_id', $partyId)->vigentes();

        return (clone $vigentes)->exists() && ! (clone $vigentes)->where('es_principal', true)->exists();
    }

    /**
     * Las sedes activas, por nombre. Para elegir dónde entregar (despachos, etapa 2): la dirección fiscal no es
     * una sede, y la de una obra es del proyecto.
     *
     * @return list<ResumenSede>
     */
    public function sedesActivas(int $partyId): array
    {
        return PartySede::query()
            ->where('party_id', $partyId)->where('es_activa', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (PartySede $s): ResumenSede => new ResumenSede($s->id, "{$s->nombre} · {$s->direccion}", $s->codigo_establecimiento))
            ->values()->all();
    }

    /**
     * La cuenta principal de la moneda para pagar o cobrar. Nunca la de detracciones (plan 1.3b, D2).
     * Si Gerencia decide que una cuenta nueva necesita aprobación antes de recibir pagos (`pendientes.md`),
     * aquí y en cuentasDePago() se agrega el filtro «solo aprobadas»: en un solo lugar.
     */
    public function cuentaPrincipal(int $partyId, string $codigoMoneda): ?ResumenCuentaBancaria
    {
        $cuenta = $this->consultaCuentasDePago($partyId)
            ->where('es_principal', true)
            ->whereHas('moneda', fn (Builder $q): Builder => $q->where('codigo', $codigoMoneda))
            ->first();

        return $cuenta === null ? null : self::resumenCuenta($cuenta);
    }

    /**
     * Las cuentas activas para pagar o cobrar, las principales primero. Nunca la de detracciones (D2). Ver la nota
     * de cuentaPrincipal() sobre «solo aprobadas».
     *
     * @return list<ResumenCuentaBancaria>
     */
    public function cuentasDePago(int $partyId): array
    {
        return $this->consultaCuentasDePago($partyId)
            ->orderByDesc('es_principal')->orderBy('id')
            ->get()
            ->map(fn (PartyCuentaBancaria $c): ResumenCuentaBancaria => self::resumenCuenta($c))
            ->values()->all();
    }

    /** La cuenta de detracciones activa (Banco de la Nación), la única que devuelve la de detracciones. */
    public function cuentaDetracciones(int $partyId): ?ResumenCuentaBancaria
    {
        // Solo si su banco sigue siendo el de detracciones: si contabilidad cambia el parámetro, la del banco anterior
        // deja de darse (revisión de la 1.3b).
        $cuenta = PartyCuentaBancaria::query()->with(['banco', 'moneda'])
            ->where('party_id', $partyId)->where('es_activa', true)->where('es_detraccion', true)
            ->whereHas('banco', fn (Builder $q): Builder => $q->where('numero_documento', $this->parametros->rucBancoDetracciones()))
            ->first();

        return $cuenta === null ? null : self::resumenCuenta($cuenta);
    }

    /**
     * Valida los datos y arma la party sin guardarla.
     *
     * @throws PartyRechazada|DocumentoIdentidadInvalido
     */
    private function construir(DatosParty $datos): Party
    {
        $tipoDocumento = $this->tipoDocumentoActivo($datos->codigoTipoDocumento);
        $documento = DocumentoIdentidad::de($datos->codigoTipoDocumento, $datos->numeroDocumento, $tipoDocumento->longitud, $tipoDocumento->nombre);
        $tipo = self::tipoPersona($documento, $datos->tipo);

        $party = new Party([
            'tipo' => $tipo,
            'tipo_documento_id' => $tipoDocumento->id,
            'numero_documento' => $documento->numero,
            'nombre_comercial' => self::texto($datos->nombreComercial),
            'direccion_fiscal' => self::texto($datos->direccionFiscal),
            'ubigeo_id' => $datos->ubigeoId,
            'rubro_id' => $this->rubroDisponible($datos->rubroId),
            'telefono' => self::texto($datos->telefono),
            'email' => self::texto($datos->email),
            'estado_sunat' => self::texto($datos->estadoSunat),
            'condicion_sunat' => self::texto($datos->condicionSunat),
        ]);

        if ($tipo === TipoPersona::Natural) {
            if (self::texto($datos->razonSocial) !== null) {
                throw PartyRechazada::razonSocialDeNatural();
            }

            $party->fill(['nombres' => self::texto($datos->nombres), 'apellidos' => self::texto($datos->apellidos)]);
        } else {
            if (self::texto($datos->nombres) !== null || self::texto($datos->apellidos) !== null) {
                throw PartyRechazada::juridicaConNombres();
            }

            $party->razon_social = (string) self::texto($datos->razonSocial);
        }

        $this->completarNombre($party);
        $this->validarContacto($party);

        return $party;
    }

    private function tipoDocumentoActivo(string $codigo): TipoDocumentoIdentidad
    {
        return TipoDocumentoIdentidad::query()->where('codigo', $codigo)->where('es_activo', true)->first()
            ?? throw PartyRechazada::tipoDocumentoNoDisponible($codigo);
    }

    private static function tipoPersona(DocumentoIdentidad $documento, ?TipoPersona $dado): TipoPersona
    {
        $deducido = $documento->tipoPersona();

        if ($deducido === null) {
            return $dado ?? throw PartyRechazada::faltaTipoPersona();
        }

        if ($dado !== null && $dado !== $deducido) {
            throw PartyRechazada::tipoPersonaNoCorresponde(mb_strtolower($deducido->etiqueta()));
        }

        return $deducido;
    }

    /** Natural: exige nombres y apellidos y arma la razón social. Jurídica: exige la razón social. */
    private function completarNombre(Party $party): void
    {
        if ($party->tipo === TipoPersona::Natural) {
            if ($party->nombres === null || $party->apellidos === null) {
                throw PartyRechazada::faltanNombres();
            }

            $party->razon_social = "{$party->apellidos} {$party->nombres}";

            return;
        }

        if (self::texto($party->razon_social ?? null) === null) {
            throw PartyRechazada::faltaRazonSocial();
        }
    }

    /** Ubigeo activo al elegirlo (si no cambió, se respeta) y correo en minúsculas y bien escrito. */
    private function validarContacto(Party $party): void
    {
        if ($party->ubigeo_id !== null && ($party->isDirty('ubigeo_id') || ! $party->exists) && ! $this->catalogos->estaActivo(Catalogo::Ubigeos, $party->ubigeo_id)) {
            throw PartyRechazada::ubigeoNoDisponible();
        }

        if ($party->email !== null) {
            $party->email = mb_strtolower($party->email);

            if (filter_var($party->email, FILTER_VALIDATE_EMAIL) === false) {
                throw PartyRechazada::emailInvalido($party->email);
            }
        }
    }

    private function cambiarEstado(int $id, bool $activa, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $activa, $versionLeida): void {
            $party = $this->bloquear($id, $versionLeida);

            if ($party->es_activo === $activa) {
                throw PartyRechazada::yaEstaEnEseEstado($activa);
            }

            // La ficha de Techquk no se desactiva: los documentos se quedarían sin sus datos (plan 1.15, D3).
            if (! $activa && app(EmpresaService::class)->esLaFicha($party)) {
                throw PartyRechazada::esLaFichaDeLaEmpresa('desactivarla');
            }

            // Una persona con cuenta activa no se desactiva: primero la cuenta, en «Usuarios», con sus reglas (plan 1.4).
            // La fila de la persona va primero, como en SeguridadService al activar una cuenta: nunca se cruzan.
            if (! $activa && User::query()->where('party_id', $party->id)->where('es_activo', true)->lockForUpdate()->exists()) {
                throw PartyRechazada::conCuentaActiva();
            }

            $party->es_activo = $activa;
            $this->guardarConNuevaVersion($party);
        });
    }

    /** Un rubro que existe y está activo (el catálogo es cerrado: en la práctica, siempre lo está). */
    private function rubroDisponible(?int $rubroId): ?int
    {
        if ($rubroId !== null && ! Rubro::query()->whereKey($rubroId)->where('es_activo', true)->exists()) {
            throw PartyRechazada::rubroNoDisponible();
        }

        return $rubroId;
    }

    /** Lee la party con la fila bloqueada y confirma que sigue en la versión que la persona leyó. */
    private function bloquear(int $id, int $versionLeida): Party
    {
        $party = Party::query()->lockForUpdate()->find($id) ?? throw PartyRechazada::noExiste();

        if ($party->version !== $versionLeida) {
            throw VersionDesactualizada::de("«{$party->razon_social}»");
        }

        return $party;
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @param  list<string>  $permitidos
     */
    private function rechazarAjenos(array $cambios, array $permitidos): void
    {
        foreach (array_keys($cambios) as $campo) {
            if (! in_array($campo, $permitidos, true)) {
                throw PartyRechazada::campoNoEditable($campo);
            }
        }
    }

    /** La cuenta de la persona lleva su nombre (plan 1.4, D4): cambia en la misma transacción que la ficha. */
    private function sincronizarNombreDeUsuario(Party $party): void
    {
        if (! $party->wasChanged('razon_social')) {
            return;
        }

        $usuario = User::query()->where('party_id', $party->id)->lockForUpdate()->first();

        if ($usuario !== null) {
            $usuario->name = $party->razon_social;
            $usuario->save();
        }
    }

    private function guardarConNuevaVersion(Party $party): void
    {
        if (! $party->isDirty()) {
            return;
        }

        $party->version++;
        $party->save();
    }

    /**
     * @return Builder<PartyCuentaBancaria>
     */
    private function consultaCuentasDePago(int $partyId): Builder
    {
        // Y solo en un banco que sigue activo y con el rol vigente (revisión de la 1.3b).
        return PartyCuentaBancaria::query()->with(['banco', 'moneda'])
            ->where('party_id', $partyId)->where('es_activa', true)->where('es_detraccion', false)
            ->whereHas('banco', fn (Builder $q): Builder => $q->where('es_activo', true)->conRolVigente('banco', Calendario::hoy()));
    }

    private static function resumenCuenta(PartyCuentaBancaria $cuenta): ResumenCuentaBancaria
    {
        $banco = $cuenta->banco->nombre_comercial ?? $cuenta->banco->razon_social;

        return new ResumenCuentaBancaria(
            $cuenta->id,
            "{$banco} · {$cuenta->numero_cuenta} · {$cuenta->moneda->codigo}",
            $cuenta->moneda->codigo,
            $cuenta->numero_cuenta,
            $cuenta->cci,
            $cuenta->es_detraccion,
        );
    }

    private static function resumen(Party $party): ResumenParty
    {
        return new ResumenParty($party->id, self::etiqueta($party), $party->es_activo);
    }

    /** «razón social · número de documento» (D6). */
    private static function etiqueta(Party $party): string
    {
        return "{$party->razon_social} · {$party->numero_documento}";
    }

    /** Texto sin espacios alrededor; vacío es nulo. */
    private static function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private static function entero(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }
}
