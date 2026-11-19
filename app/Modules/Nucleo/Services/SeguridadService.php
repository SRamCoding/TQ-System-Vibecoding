<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Events\ControlCruceRegistrado;
use App\Modules\Nucleo\Events\CruceFuncionesCerrado;
use App\Modules\Nucleo\Events\CruceFuncionesRegistrado;
use App\Modules\Nucleo\Events\PermisosDeRolCambiados;
use App\Modules\Nucleo\Events\RolAsignado;
use App\Modules\Nucleo\Events\RolQuitado;
use App\Modules\Nucleo\Excepciones\CruceSinControl;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Excepciones\OrganigramaRechazado;
use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Seguridad\Ambitos;
use App\Modules\Nucleo\Seguridad\DatosControl;
use App\Modules\Nucleo\Seguridad\DatosUsuario;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as Consulta;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * M10 · Usuarios, roles, permisos por ámbito y separación de funciones (plan 1.4, D1, D4 y D7-D9). Interno del
 * núcleo: los demás módulos escriben por las acciones registradas y preguntan con `puede()` y `ambitosDonde()`.
 *
 * - Una persona ve la suma de sus roles: los globales (spatie) y los acotados a un ámbito, vigentes hoy en Lima (el
 *   día `hasta` cuenta). Los permisos llegan solo por roles; los directos de spatie se cuentan para no esconder nada.
 * - Separación de funciones: un par del catálogo de permisos incompatibles que una persona junta es un cruce. Para
 *   detectarlo cuentan sus roles globales y los acotados vigentes o futuros, para que no se esconda en un ámbito.
 *   Asignar un rol, darlo en un ámbito o agregar un permiso a un rol exige el control compensatorio de cada cruce
 *   nuevo; agregar un par al catálogo no se bloquea y deja los cruces pendientes. Perder un permiso cierra el cruce.
 * - Nadie cambia sus propios roles ni los permisos de un rol que tiene (D8), ni su puesto, ni la cuenta propia, ni
 *   desactiva el par de un cruce suyo, ni registra el control de su propio cruce (revisión de la 1.4). El rol
 *   `administrador` no se renombra ni cambia sus permisos. Nunca queda sin nadie activo que pueda gestionar usuarios.
 * - El rol `gerencia general` (plan 1.10, D2) viene con el sistema, sin permisos (los decide la grilla): recibe las
 *   alertas de control. Se registra al instalar; no se renombra, no se da en un ámbito y nunca queda sin una persona
 *   activa que lo tenga (si antes la había).
 * - Todo cambio corre con un candado único (`pg_advisory_xact_lock`): dos cambios de roles a la vez podrían, cada
 *   uno por su lado, no ver el cruce o el último administrador que juntos producen.
 */
final class SeguridadService
{
    public const ROL_ADMINISTRADOR = 'administrador';

    public const ROL_GERENCIA_GENERAL = 'gerencia general';

    public const PERMISO_GESTIONAR_USUARIOS = 'usuarios.gestionar';

    /** Tipos de ámbito (alias del morph map) en que se acota un rol: el mismo CHECK de `permisos_por_ambito`. */
    public const AMBITOS = ['unidad_organizativa'];

    private const CANDADO = 'nucleo.seguridad';

    private const LARGO_MINIMO_CLAVE = 12;

    /** Documentos de una persona que puede tener cuenta (D4): nunca un RUC. */
    private const DOCUMENTOS_PERSONALES = [Catalogo::DNI, DocumentoIdentidad::CARNE_EXTRANJERIA, DocumentoIdentidad::PASAPORTE];

    public function __construct(
        private readonly OrganigramaService $organigrama,
        private readonly PermissionRegistrar $permisos,
        private readonly HistorialCambiosService $historial,
        private readonly PartiesService $parties,
    ) {}

    // --- Consultas ---

    /**
     * Si la persona tiene el permiso hoy: por un rol global o, si se da un ámbito, por un rol acotado a ese ámbito y
     * vigente. Una cuenta desactivada o de sistema no puede nada.
     */
    public function puede(User $usuario, string $permiso, ?Model $ambito = null): bool
    {
        $permisoId = self::idPermiso($permiso);

        if (! $usuario->es_activo || $usuario->es_sistema || $permisoId === null) {
            return false;
        }

        if (in_array($permisoId, $this->idsGlobales($usuario->id), true)) {
            return true;
        }

        return $ambito !== null && $this->acotados($usuario->id, futuros: false)
            ->where('rp.permission_id', $permisoId)
            ->where('pa.ambito_type', $ambito->getMorphClass())
            ->where('pa.ambito_id', $ambito->getKey())
            ->exists();
    }

    /**
     * Dónde tiene hoy la persona el permiso, en ámbitos de un tipo (`unidad_organizativa`): todos si se lo da un
     * rol global; si no, los ids de sus ámbitos vigentes.
     */
    public function ambitosDonde(User $usuario, string $permiso, string $tipoAmbito): Ambitos
    {
        $permisoId = self::idPermiso($permiso);

        if (! $usuario->es_activo || $usuario->es_sistema || $permisoId === null) {
            return Ambitos::solo([]);
        }

        if (in_array($permisoId, $this->idsGlobales($usuario->id), true)) {
            return Ambitos::todos();
        }

        return Ambitos::solo($this->acotados($usuario->id, futuros: false)
            ->where('rp.permission_id', $permisoId)
            ->where('pa.ambito_type', $tipoAmbito)
            ->pluck('pa.ambito_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Lo propio y lo de todos (plan 2.20a, N5; docs/convenciones/codigo.md): puede con el permiso de todas, o con el de
     * propias si es el responsable del registro (el ejecutivo de una cuenta). Tener los dos es tener el de todas. Solo
     * cuentan los roles globales: «propias» no es un ámbito de `permisos_por_ambito`. Si algún día lo es, solo cambian
     * este método y filtrarPropios().
     */
    public function puedeSobrePropio(User $usuario, string $permisoTodas, string $permisoPropias, int $responsableUserId): bool
    {
        return $this->puede($usuario, $permisoTodas)
            || ($usuario->id === $responsableUserId && $this->puede($usuario, $permisoPropias));
    }

    /**
     * La pareja de puedeSobrePropio() para una lista: deja en la consulta solo lo que la persona puede tocar, en la
     * base y no registro por registro. Con el permiso de todas no la toca; con el de propias, solo donde
     * `$columnaResponsable` es ella; sin ninguno, nada.
     *
     * @template TModelo of Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    public function filtrarPropios(Builder $consulta, User $usuario, string $permisoTodas, string $permisoPropias, string $columnaResponsable): Builder
    {
        if ($this->puede($usuario, $permisoTodas)) {
            return $consulta;
        }

        return $this->puede($usuario, $permisoPropias)
            ? $consulta->where($columnaResponsable, $usuario->id)
            : $consulta->whereRaw('false');
    }

    /**
     * Los cruces que hoy piden atención: abiertos, de un par activo, con los dos permisos vigentes hoy y sin un
     * control actual vigente (D9). Un rol acotado futuro todavía no cuenta.
     *
     * @return Collection<int, CruceFunciones>
     */
    public function crucesPendientes(): Collection
    {
        return CruceFunciones::query()
            ->whereNull('hasta')
            ->whereHas('incompatibilidad', fn ($q) => $q->where('es_activa', true))
            ->with(['incompatibilidad', 'usuario'])
            ->orderBy('id')
            ->get()
            ->filter(function (CruceFunciones $cruce): bool {
                $vigentes = $this->idsEfectivos($cruce->user_id, futuros: false);

                return in_array($cruce->incompatibilidad->permiso_a_id, $vigentes, true)
                    && in_array($cruce->incompatibilidad->permiso_b_id, $vigentes, true)
                    && ! $this->estaControlado($cruce);
            })
            ->values();
    }

    /**
     * Cierra los cruces de quienes ya no juntan los dos permisos sin que un cambio lo dispare: venció su rol acotado
     * (plan 1.4, D9). Lo corre la revisión diaria de alertas (plan 1.10, D4), antes de contar los pendientes. Devuelve
     * cuántos cerró.
     */
    public function cerrarCrucesVencidos(): int
    {
        return $this->conCandado(function (): int {
            $usuarioIds = CruceFunciones::query()->whereNull('hasta')->distinct()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
            $abiertos = CruceFunciones::query()->whereNull('hasta')->count();
            $this->revisarCruces($usuarioIds, null, exigirControl: false);

            return $abiertos - CruceFunciones::query()->whereNull('hasta')->count();
        });
    }

    /**
     * Quiénes tienen cada rol: global, o acotado sin terminar. Para las pantallas: de una vez, no rol por rol.
     *
     * @return array<int, list<int>> rol => cuentas
     */
    public function titularesPorRol(): array
    {
        $globales = DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->select('role_id as rol', 'model_id as usuario');
        $filas = DB::table('permisos_por_ambito')
            ->where(fn (Consulta $q): Consulta => $q->whereNull('hasta')->orWhere('hasta', '>=', Calendario::hoy()))
            ->select('rol_id as rol', 'user_id as usuario')
            ->union($globales)
            ->get();

        return $filas->groupBy('rol')->map(fn ($deUnRol) => $deUnRol->pluck('usuario')->map(fn (mixed $id): int => (int) $id)->values()->all())->all();
    }

    /** Controlado: tiene un control actual que no venció (el día `vigente_hasta` todavía vale). */
    public function estaControlado(CruceFunciones $cruce): bool
    {
        return CruceFunciones::query()->whereKey($cruce->id)->scopes('conControlVigente')->exists();
    }

    // --- Usuarios ---

    /**
     * Crea la cuenta de una persona (ficha de DNI, CE o pasaporte, activa) con su correo y, si se da, su puesto. La
     * clave es aleatoria: la persona pone la suya con «olvidé mi clave». Devuelve su id.
     *
     * @throws SeguridadRechazada|OrganigramaRechazado
     */
    public function registrarUsuario(DatosUsuario $datos): int
    {
        return $this->conCandado(function () use ($datos): int {
            $party = Party::query()->lockForUpdate()->find($datos->partyId) ?? throw SeguridadRechazada::personaNoExiste();
            $codigoDocumento = TipoDocumentoIdentidad::query()->whereKey($party->tipo_documento_id)->value('codigo');

            if (! in_array($codigoDocumento, self::DOCUMENTOS_PERSONALES, true)) {
                throw SeguridadRechazada::noEsPersonaNatural();
            }

            if (! $party->es_activo) {
                throw SeguridadRechazada::personaInactiva();
            }

            if ($datos->puestoId !== null) {
                $this->organigrama->bloquearParaOcupar($datos->puestoId, null);
            }

            $usuario = new User([
                'name' => $party->razon_social,
                'email' => self::correo($datos->email),
                'password' => Str::random(64),
                'party_id' => $party->id,
                'puesto_id' => $datos->puestoId,
            ]);
            $this->guardarUsuario($usuario);

            return $usuario->id;
        });
    }

    /**
     * Pone la clave de una cuenta. Solo la usa `seguridad:primer-administrador`: las demás personas ponen la suya con
     * «¿Olvidaste tu contraseña?». Nunca se registra ni se devuelve.
     *
     * @throws SeguridadRechazada
     */
    public function establecerClave(int $usuarioId, #[SensitiveParameter] string $clave): void
    {
        if (mb_strlen($clave) < self::LARGO_MINIMO_CLAVE) {
            throw SeguridadRechazada::claveCorta(self::LARGO_MINIMO_CLAVE);
        }

        // Además del largo del administrador, la regla de todo el sistema (plan 1.4h, D3).
        $faltas = ClaveService::faltas($clave);

        if ($faltas !== []) {
            throw SeguridadRechazada::claveDebil($faltas);
        }

        $this->conCandado(function () use ($usuarioId, $clave): void {
            $usuario = User::query()->lockForUpdate()->find($usuarioId) ?? throw SeguridadRechazada::usuarioNoExiste();

            if ($usuario->es_sistema) {
                throw SeguridadRechazada::usuarioDeSistema();
            }

            $usuario->password = $clave;
            $usuario->debe_cambiar_clave = false;
            $usuario->clave_cambiada_at = now()->toImmutable();
            $usuario->version++;
            $this->guardarUsuario($usuario);
        });
    }

    /**
     * La primera cuenta de administrador (D8), para `seguridad:primer-administrador`: su persona (o la que ya exista
     * con ese documento), su cuenta, su clave y el rol, a nombre del usuario de sistema. Bajo el candado de seguridad:
     * dos corridas a la vez no crean dos. Se niega si ya hay un administrador activo. Devuelve el id de la cuenta.
     *
     * @throws SeguridadRechazada|ErrorDeNegocio
     */
    public function registrarPrimerAdministrador(DatosParty $persona, string $email, string $clave, User $sistema): int
    {
        return $this->conCandado(function () use ($persona, $email, $clave, $sistema): int {
            if ($this->hayAdministradorActivo()) {
                throw SeguridadRechazada::yaHayAdministrador();
            }

            return $this->registrarCuentaInicial(self::ROL_ADMINISTRADOR, $persona, $email, $clave, $sistema);
        });
    }

    /**
     * La cuenta del gerente general (plan 1.10, D2), para `seguridad:gerente-general`: igual que el primer
     * administrador, con el rol `gerencia general`. Se niega si ya hay un gerente general activo: desde ahí, el
     * siguiente se lo asigna un administrador en «Usuarios».
     *
     * @throws SeguridadRechazada|ErrorDeNegocio
     */
    public function registrarGerenteGeneral(DatosParty $persona, string $email, string $clave, User $sistema): int
    {
        return $this->conCandado(function () use ($persona, $email, $clave, $sistema): int {
            if ($this->hayGerenteGeneralActivo()) {
                throw SeguridadRechazada::yaHayGerenteGeneral();
            }

            return $this->registrarCuentaInicial(self::ROL_GERENCIA_GENERAL, $persona, $email, $clave, $sistema);
        });
    }

    /**
     * Si hay alguna cuenta activa (de persona) con el rol `administrador`.
     */
    public function hayAdministradorActivo(): bool
    {
        return $this->titularesActivos(self::ROL_ADMINISTRADOR) > 0;
    }

    /**
     * Si hay alguna cuenta activa (de persona) con el rol `gerencia general`, global. Sin ella el panel no arranca.
     */
    public function hayGerenteGeneralActivo(): bool
    {
        return $this->titularesActivos(self::ROL_GERENCIA_GENERAL) > 0;
    }

    /**
     * Cambia el puesto de otra persona, o la deja sin puesto. Un puesto de jefatura es de una sola persona activa.
     *
     * @throws SeguridadRechazada|OrganigramaRechazado|VersionDesactualizada
     */
    public function cambiarPuesto(int $usuarioId, ?int $puestoId, int $versionLeida, User $actor): void
    {
        $this->conCandado(function () use ($usuarioId, $puestoId, $versionLeida, $actor): void {
            // Nadie se cambia su propio puesto: una jefatura pesa en las aprobaciones (1.7).
            $usuario = $this->bloquearAjeno($usuarioId, $actor);

            if ($usuario->version !== $versionLeida) {
                throw VersionDesactualizada::de("la cuenta de {$usuario->name}");
            }

            if ($puestoId !== null && $usuario->es_activo) {
                $this->organigrama->bloquearParaOcupar($puestoId, $usuario->id);
            }

            $usuario->puesto_id = $puestoId;

            if ($usuario->isDirty()) {
                $usuario->version++;
                $this->guardarUsuario($usuario);
            }
        });
    }

    /**
     * La cuenta deja de iniciar sesión; sus roles quedan. Nunca la propia ni la del último administrador activo.
     *
     * @throws SeguridadRechazada|VersionDesactualizada
     */
    public function desactivarUsuario(int $usuarioId, int $versionLeida, User $actor): void
    {
        $this->cambiarEstado($usuarioId, false, $versionLeida, $actor);
    }

    /**
     * @throws SeguridadRechazada|OrganigramaRechazado|VersionDesactualizada
     */
    public function activarUsuario(int $usuarioId, int $versionLeida, User $actor): void
    {
        $this->cambiarEstado($usuarioId, true, $versionLeida, $actor);
    }

    // --- Roles de una persona ---

    /**
     * Da un rol global. Si con él la persona junta permisos incompatibles, exige el control de cada cruce nuevo.
     *
     * @throws SeguridadRechazada|CruceSinControl
     */
    public function asignarRol(int $usuarioId, int $rolId, ?DatosControl $control, User $actor): void
    {
        $this->conCandado(function () use ($usuarioId, $rolId, $control, $actor): void {
            $usuario = $this->bloquearAjeno($usuarioId, $actor);
            $rol = self::rol($rolId);

            if ($this->tieneRolGlobal($usuario->id, $rol->id)) {
                throw SeguridadRechazada::yaTieneElRol($rol->name);
            }

            $this->validarControl($control);
            $antes = self::rolesGlobales($usuario);
            $usuario->assignRole($rol);
            $this->anotarRoles($usuario, $antes);
            $this->revisarCruces([$usuario->id], $control, exigirControl: true);
            RolAsignado::dispatch($actor->id, $usuario->id, $rol->id);
        });
    }

    /**
     * Quita un rol global. Cierra los cruces que la persona deja de juntar.
     *
     * @throws SeguridadRechazada
     */
    public function quitarRol(int $usuarioId, int $rolId, User $actor): void
    {
        $this->conCandado(function () use ($usuarioId, $rolId, $actor): void {
            $usuario = $this->bloquearAjeno($usuarioId, $actor);
            $rol = self::rol($rolId);

            if (! $this->tieneRolGlobal($usuario->id, $rol->id)) {
                throw SeguridadRechazada::noTieneElRol($rol->name);
            }

            $antes = self::rolesGlobales($usuario);
            $usuario->removeRole($rol);
            $this->anotarRoles($usuario, $antes);
            $this->revisarCruces([$usuario->id], null, exigirControl: false);
            RolQuitado::dispatch($actor->id, $usuario->id, $rol->id);
        }, cuidaTitulares: true);
    }

    /**
     * Da un rol solo dentro de un ámbito (una unidad organizativa), con vigencia opcional. Devuelve el id de la
     * asignación. Exige el control de cada cruce nuevo, igual que un rol global.
     *
     * @throws SeguridadRechazada|CruceSinControl
     */
    public function asignarRolEnAmbito(
        int $usuarioId,
        int $rolId,
        string $tipoAmbito,
        int $ambitoId,
        ?CarbonImmutable $desde,
        ?CarbonImmutable $hasta,
        ?DatosControl $control,
        User $actor,
    ): int {
        return $this->conCandado(function () use ($usuarioId, $rolId, $tipoAmbito, $ambitoId, $desde, $hasta, $control, $actor): int {
            $usuario = $this->bloquearAjeno($usuarioId, $actor);
            $rol = self::rol($rolId);

            if ($rol->name === self::ROL_GERENCIA_GENERAL) {
                throw SeguridadRechazada::rolGerenciaSoloGlobal();
            }

            self::exigirAmbitoDisponible($tipoAmbito, $ambitoId);

            if (($desde !== null && $hasta !== null && $hasta->lt($desde)) || ($hasta !== null && $hasta->toDateString() < Calendario::hoy())) {
                throw SeguridadRechazada::fechasInvalidas();
            }

            $this->validarControl($control);
            $asignacion = new PermisoPorAmbito([
                'user_id' => $usuario->id,
                'rol_id' => $rol->id,
                'ambito_type' => $tipoAmbito,
                'ambito_id' => $ambitoId,
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ]);

            try {
                DB::transaction(fn (): bool => $asignacion->save());
            } catch (QueryException $e) {
                // 23P01: exclusion_violation (el mismo rol en el mismo ámbito, en fechas que se cruzan).
                throw ($e->errorInfo[0] ?? null) === '23P01' ? SeguridadRechazada::rolEnAmbitoRepetido() : $e;
            }

            $this->revisarCruces([$usuario->id], $control, exigirControl: true);
            RolAsignado::dispatch($actor->id, $usuario->id, $rol->id, $tipoAmbito, $ambitoId, $asignacion->desde?->toDateString(), $asignacion->hasta?->toDateString());

            return $asignacion->id;
        });
    }

    /**
     * Termina un rol acotado: deja de valer hoy (su último día es ayer). Si todavía no empezaba, se borra.
     *
     * @throws SeguridadRechazada|VersionDesactualizada
     */
    public function quitarRolEnAmbito(int $asignacionId, int $versionLeida, User $actor): void
    {
        $this->conCandado(function () use ($asignacionId, $versionLeida, $actor): void {
            $asignacion = PermisoPorAmbito::query()->lockForUpdate()->find($asignacionId) ?? throw SeguridadRechazada::asignacionNoExiste();
            $this->bloquearAjeno($asignacion->user_id, $actor);

            if ($asignacion->version !== $versionLeida) {
                throw VersionDesactualizada::de('la asignación del rol');
            }

            $hoy = Calendario::hoy();

            if ($asignacion->hasta !== null && $asignacion->hasta->toDateString() < $hoy) {
                throw SeguridadRechazada::asignacionTerminada();
            }

            if ($asignacion->desde !== null && $asignacion->desde->toDateString() >= $hoy) {
                DB::transaction(fn (): ?bool => $asignacion->delete());
            } else {
                $asignacion->hasta = CarbonImmutable::parse(Calendario::ayer());
                $asignacion->version++;
                DB::transaction(fn (): bool => $asignacion->save());
            }

            $this->revisarCruces([$asignacion->user_id], null, exigirControl: false);
            RolQuitado::dispatch($actor->id, $asignacion->user_id, $asignacion->rol_id, $asignacion->ambito_type, $asignacion->ambito_id);
        });
    }

    // --- Roles y sus permisos ---

    /**
     * @throws SeguridadRechazada
     */
    public function crearRol(string $nombre): int
    {
        return $this->conCandado(function () use ($nombre): int {
            $rol = new Role(['name' => self::nombre($nombre), 'guard_name' => 'web']);
            $this->guardarRol($rol);
            $this->historial->registrar($rol, EventoAuditoria::Creado, null, ['nombre' => $rol->name]);

            return (int) $rol->getKey();
        });
    }

    /**
     * @throws SeguridadRechazada
     */
    public function renombrarRol(int $rolId, string $nombre): void
    {
        $this->conCandado(function () use ($rolId, $nombre): void {
            $rol = self::rol($rolId);

            if ($rol->name === self::ROL_ADMINISTRADOR) {
                throw SeguridadRechazada::rolAdministradorFijo();
            }

            if ($rol->name === self::ROL_GERENCIA_GENERAL) {
                throw SeguridadRechazada::rolGerenciaFijo();
            }

            $anterior = $rol->name;
            $rol->name = self::nombre($nombre);
            $this->guardarRol($rol);

            if ($rol->name !== $anterior) {
                $this->historial->registrar($rol, EventoAuditoria::Actualizado, ['nombre' => $anterior], ['nombre' => $rol->name]);
            }
        });
    }

    /**
     * Deja al rol exactamente con estos permisos. Revisa los cruces de todas las personas que lo tienen, global o
     * acotado (vigente o futuro): exige el control de cada cruce nuevo y cierra los que dejan de existir.
     *
     * @param  list<int>  $permisoIds
     *
     * @throws SeguridadRechazada|CruceSinControl
     */
    public function cambiarPermisosDeRol(int $rolId, array $permisoIds, ?DatosControl $control, User $actor): void
    {
        $this->conCandado(function () use ($rolId, $permisoIds, $control, $actor): void {
            $rol = self::rol($rolId);

            if ($rol->name === self::ROL_ADMINISTRADOR) {
                throw SeguridadRechazada::rolAdministradorFijo();
            }

            $titulares = $this->titularesDelRol($rol->id);

            if (in_array($actor->id, $titulares, true)) {
                throw SeguridadRechazada::rolPropio($rol->name);
            }

            $this->validarControl($control);
            $antes = self::nombresDePermisos($rol);
            $rol->syncPermissions(Permission::query()->whereKey(array_values(array_unique($permisoIds)))->get());
            $despues = self::nombresDePermisos($rol);
            $this->revisarCruces($titulares, $control, exigirControl: true);

            if ($antes !== $despues) {
                $this->historial->registrar($rol, EventoAuditoria::Actualizado, ['permisos' => $antes], ['permisos' => $despues]);
                PermisosDeRolCambiados::dispatch($actor->id, $rol->id, $antes, $despues);
            }
        }, cuidaTitulares: true);
    }

    // --- Separación de funciones ---

    /**
     * Agrega un par de permisos incompatibles (o reactiva uno desactivado). No se bloquea: quien ya juntaba los dos
     * queda con un cruce pendiente de control. Devuelve el id del par.
     *
     * @throws SeguridadRechazada
     */
    public function registrarIncompatibilidad(int $permisoUnoId, int $permisoOtroId, string $motivo): int
    {
        return $this->conCandado(function () use ($permisoUnoId, $permisoOtroId, $motivo): int {
            if ($permisoUnoId === $permisoOtroId) {
                throw SeguridadRechazada::mismoPermiso();
            }

            [$a, $b] = [min($permisoUnoId, $permisoOtroId), max($permisoUnoId, $permisoOtroId)];
            $par = IncompatibilidadPermisos::query()->lockForUpdate()->where('permiso_a_id', $a)->where('permiso_b_id', $b)->first();

            if ($par?->es_activa === true) {
                throw SeguridadRechazada::parRepetido();
            }

            $par ??= new IncompatibilidadPermisos(['permiso_a_id' => $a, 'permiso_b_id' => $b]);
            $par->fill(['motivo' => trim($motivo) === '' ? throw SeguridadRechazada::faltaMotivo() : trim($motivo), 'es_activa' => true]);

            if ($par->exists) {
                $par->version++;
            }

            DB::transaction(fn (): bool => $par->save());
            $this->revisarCruces(User::query()->where('es_sistema', false)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(), null, exigirControl: false);

            return $par->id;
        });
    }

    /**
     * El par deja de revisarse; sus cruces abiertos se cierran (la historia queda). Nunca quien tiene un cruce abierto
     * de ese par.
     *
     * @throws SeguridadRechazada|VersionDesactualizada
     */
    public function desactivarIncompatibilidad(int $id, int $versionLeida, User $actor): void
    {
        $this->conCandado(function () use ($id, $versionLeida, $actor): void {
            $par = IncompatibilidadPermisos::query()->lockForUpdate()->find($id) ?? throw SeguridadRechazada::parNoExiste();

            // Nadie apaga el par de un cruce suyo: lo cerraría.
            if (CruceFunciones::query()->where('incompatibilidad_id', $par->id)->where('user_id', $actor->id)->whereNull('hasta')->exists()) {
                throw SeguridadRechazada::parPropio();
            }

            if ($par->version !== $versionLeida) {
                throw VersionDesactualizada::de('el par de permisos incompatibles');
            }

            if (! $par->es_activa) {
                throw SeguridadRechazada::parYaDesactivado();
            }

            $par->es_activa = false;
            $par->version++;
            DB::transaction(fn (): bool => $par->save());

            $afectados = CruceFunciones::query()->where('incompatibilidad_id', $par->id)->whereNull('hasta')->pluck('user_id');
            $this->revisarCruces($afectados->map(fn (mixed $id): int => (int) $id)->all(), null, exigirControl: false);
        });
    }

    /**
     * Registra el control de un cruce abierto, o lo renueva: el anterior deja de ser el actual. Nunca la persona del
     * cruce. Devuelve su id.
     *
     * @throws SeguridadRechazada
     */
    public function registrarControl(int $cruceId, DatosControl $control, User $actor): int
    {
        return $this->conCandado(function () use ($cruceId, $control, $actor): int {
            $cruce = CruceFunciones::query()->lockForUpdate()->find($cruceId) ?? throw SeguridadRechazada::cruceNoExiste();

            if ($cruce->user_id === $actor->id) {
                throw SeguridadRechazada::controlPropio();
            }

            if ($cruce->hasta !== null) {
                throw SeguridadRechazada::cruceCerrado();
            }

            $this->validarControl($control);

            return $this->crearControl($cruce, $control);
        });
    }

    // --- Internos ---

    /**
     * Corre el cambio con el candado de seguridad, en una transacción. Si `cuidaTitulares`, rechaza el cambio que deja
     * sin nadie activo que pueda gestionar usuarios, o sin gerente general activo (si antes había alguien).
     *
     * @template TResultado
     *
     * @param  Closure(): TResultado  $cambio
     * @return TResultado
     */
    private function conCandado(Closure $cambio, bool $cuidaTitulares = false): mixed
    {
        return DB::transaction(function () use ($cambio, $cuidaTitulares): mixed {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [self::CANDADO]);
            $antes = $cuidaTitulares ? $this->administradoresActivos() : 0;
            $gerentesAntes = $cuidaTitulares ? $this->titularesActivos(self::ROL_GERENCIA_GENERAL) : 0;

            try {
                $resultado = $cambio();
            } finally {
                $this->permisos->forgetCachedPermissions();
            }

            // Y otra vez al confirmar: entre el borrado de arriba y el commit, otra petición pudo volver a llenar la caché
            // (dura 24 h) con los permisos de antes.
            DB::afterCommit(fn () => $this->permisos->forgetCachedPermissions());

            if ($cuidaTitulares && $antes > 0 && $this->administradoresActivos() === 0) {
                throw SeguridadRechazada::sinAdministrador();
            }

            if ($cuidaTitulares && $gerentesAntes > 0 && $this->titularesActivos(self::ROL_GERENCIA_GENERAL) === 0) {
                throw SeguridadRechazada::sinGerenteGeneral();
            }

            return $resultado;
        });
    }

    private function cambiarEstado(int $usuarioId, bool $activo, int $versionLeida, User $actor): void
    {
        $this->conCandado(function () use ($usuarioId, $activo, $versionLeida, $actor): void {
            // La persona antes que la cuenta, como PartiesService al desactivarla: nunca se esperan en orden cruzado.
            $partyId = User::query()->whereKey($usuarioId)->value('party_id');
            $personaActiva = $partyId === null ? null : Party::query()->whereKey($partyId)->sharedLock()->value('es_activo');
            $usuario = $this->bloquearAjeno($usuarioId, $actor);

            if ($activo && $personaActiva === false) {
                throw SeguridadRechazada::personaInactiva();
            }

            if ($usuario->version !== $versionLeida) {
                throw VersionDesactualizada::de("la cuenta de {$usuario->name}");
            }

            if ($usuario->es_activo === $activo) {
                throw SeguridadRechazada::yaEstaEnEseEstado($activo);
            }

            // Volver a activarse ocupa de nuevo su puesto: si es una jefatura, nadie más activo puede tenerla.
            if ($activo && $usuario->puesto_id !== null) {
                $this->organigrama->bloquearParaOcupar($usuario->puesto_id, $usuario->id);
            }

            $usuario->es_activo = $activo;
            $usuario->version++;
            $this->guardarUsuario($usuario);
        }, cuidaTitulares: ! $activo);
    }

    /**
     * Abre los cruces nuevos de cada persona y cierra los que ya no junta. Con `exigirControl`, un cruce nuevo sin
     * control frena todo el cambio y dice cuáles faltan.
     *
     * @param  list<int>  $usuarioIds
     *
     * @throws CruceSinControl|SeguridadRechazada
     */
    private function revisarCruces(array $usuarioIds, ?DatosControl $control, bool $exigirControl): void
    {
        $hoy = Calendario::hoy();
        $pares = IncompatibilidadPermisos::query()->where('es_activa', true)->get();
        $faltantes = [];
        $nuevos = [];

        foreach (array_values(array_unique($usuarioIds)) as $usuarioId) {
            $permisos = $this->idsEfectivos($usuarioId, futuros: true);
            $junta = $pares->filter(fn (IncompatibilidadPermisos $par): bool => in_array($par->permiso_a_id, $permisos, true) && in_array($par->permiso_b_id, $permisos, true))->keyBy('id');
            $abiertos = CruceFunciones::query()->where('user_id', $usuarioId)->whereNull('hasta')->lockForUpdate()->get()->keyBy('incompatibilidad_id');

            foreach ($abiertos as $parId => $cruce) {
                if (! $junta->has($parId)) {
                    $cruce->hasta = CarbonImmutable::parse($hoy);
                    DB::transaction(fn (): bool => $cruce->save());
                    CruceFuncionesCerrado::dispatch($cruce->id, $usuarioId, $cruce->incompatibilidad_id);
                }
            }

            foreach ($junta as $parId => $par) {
                if ($abiertos->has($parId)) {
                    continue;
                }

                if ($exigirControl && $control === null) {
                    $faltantes[] = [
                        'usuario_id' => $usuarioId,
                        'usuario' => (string) User::query()->whereKey($usuarioId)->value('name'),
                        'incompatibilidad_id' => $par->id,
                        'permisos' => self::nombreDelPar($par),
                    ];

                    continue;
                }

                $nuevos[] = [$usuarioId, $par];
            }
        }

        if ($faltantes !== []) {
            throw new CruceSinControl($faltantes);
        }

        foreach ($nuevos as [$usuarioId, $par]) {
            $cruce = new CruceFunciones(['user_id' => $usuarioId, 'incompatibilidad_id' => $par->id, 'desde' => $hoy]);
            DB::transaction(fn (): bool => $cruce->save());

            if ($control !== null) {
                $this->crearControl($cruce, $control);
            }

            CruceFuncionesRegistrado::dispatch($cruce->id, $usuarioId, $par->id, $control !== null);
        }
    }

    private function crearControl(CruceFunciones $cruce, DatosControl $datos): int
    {
        if ($datos->responsableId === $cruce->user_id) {
            throw SeguridadRechazada::responsableEsLaPersona((string) User::query()->whereKey($cruce->user_id)->value('name'));
        }

        foreach (ControlCruce::query()->where('cruce_id', $cruce->id)->where('es_actual', true)->lockForUpdate()->get() as $anterior) {
            $anterior->es_actual = false;
            DB::transaction(fn (): bool => $anterior->save());
        }

        $control = new ControlCruce([
            'cruce_id' => $cruce->id,
            'user_id' => $cruce->user_id,
            'control' => trim($datos->control),
            'responsable_user_id' => $datos->responsableId,
            'vigente_hasta' => $datos->vigenteHasta,
            'es_actual' => true,
        ]);
        DB::transaction(fn (): bool => $control->save());
        ControlCruceRegistrado::dispatch($control->id, $cruce->id, $datos->responsableId);

        return $control->id;
    }

    private function validarControl(?DatosControl $control): void
    {
        if ($control === null) {
            return;
        }

        if (trim($control->control) === '') {
            throw SeguridadRechazada::controlVacio();
        }

        $responsableDisponible = User::query()->whereKey($control->responsableId)->where('es_activo', true)->where('es_sistema', false)->exists();

        if (! $responsableDisponible) {
            throw SeguridadRechazada::responsableNoDisponible();
        }

        if ($control->vigenteHasta !== null && $control->vigenteHasta < Calendario::hoy()) {
            throw SeguridadRechazada::controlVencido();
        }
    }

    /**
     * Ids de los permisos que da la suma de roles: globales y directos, más los acotados vigentes hoy y, con
     * `futuros`, también los que empiezan después (para los cruces).
     *
     * @return list<int>
     */
    private function idsEfectivos(int $usuarioId, bool $futuros): array
    {
        $acotados = $this->acotados($usuarioId, $futuros)->pluck('rp.permission_id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_unique([...$this->idsGlobales($usuarioId), ...$acotados]));
    }

    /**
     * @return list<int>
     */
    private function idsGlobales(int $usuarioId): array
    {
        $tipo = (new User)->getMorphClass();
        $porRol = DB::table('model_has_roles as mr')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->where('mr.model_type', $tipo)
            ->where('mr.model_id', $usuarioId)
            ->pluck('rp.permission_id');
        $directos = DB::table('model_has_permissions')->where('model_type', $tipo)->where('model_id', $usuarioId)->pluck('permission_id');

        return $porRol->merge($directos)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    /** Roles acotados de la persona que no terminaron (vigentes hoy y, con `futuros`, los que empiezan después). */
    private function acotados(int $usuarioId, bool $futuros): Consulta
    {
        $hoy = Calendario::hoy();
        $consulta = DB::table('permisos_por_ambito as pa')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'pa.rol_id')
            ->where('pa.user_id', $usuarioId)
            ->where(fn (Consulta $q): Consulta => $q->whereNull('pa.hasta')->orWhere('pa.hasta', '>=', $hoy));

        return $futuros ? $consulta : $consulta->where(fn (Consulta $q): Consulta => $q->whereNull('pa.desde')->orWhere('pa.desde', '<=', $hoy));
    }

    /**
     * Quienes tienen el rol: global, o acotado sin terminar.
     *
     * @return list<int>
     */
    private function titularesDelRol(int $rolId): array
    {
        return $this->titularesPorRol()[$rolId] ?? [];
    }

    /**
     * @return list<string>
     */
    private static function rolesGlobales(User $usuario): array
    {
        return $usuario->roles()->orderBy('name')->pluck('name')->map(fn (mixed $n): string => (string) $n)->all();
    }

    /**
     * Los roles globales no tienen autor ni bitácora en spatie: el cambio queda en la de la cuenta (plan 1.4, revisión).
     *
     * @param  list<string>  $antes
     */
    private function anotarRoles(User $usuario, array $antes): void
    {
        $usuario->unsetRelation('roles');
        $this->historial->registrar($usuario, EventoAuditoria::Actualizado, ['roles' => $antes], ['roles' => self::rolesGlobales($usuario)]);
    }

    private function tieneRolGlobal(int $usuarioId, int $rolId): bool
    {
        return DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->where('model_id', $usuarioId)->where('role_id', $rolId)->exists();
    }

    /** La persona (o la que ya exista con ese documento), su cuenta, su clave y el rol, a nombre del sistema. */
    private function registrarCuentaInicial(string $nombreRol, DatosParty $persona, string $email, string $clave, User $sistema): int
    {
        $rol = Role::query()->where('name', $nombreRol)->first() ?? throw SeguridadRechazada::sinRolDeFabrica($nombreRol);
        $partyId = Party::query()
            ->where('tipo_documento_id', TipoDocumentoIdentidad::query()->where('codigo', $persona->codigoTipoDocumento)->value('id'))
            ->where('numero_documento', DocumentoIdentidad::normalizar($persona->numeroDocumento))
            ->value('id');
        $partyId = $partyId === null ? $this->parties->registrar($persona)->id : (int) $partyId;
        $usuarioId = $this->registrarUsuario(new DatosUsuario($partyId, $email));
        $this->establecerClave($usuarioId, $clave);
        $this->asignarRol($usuarioId, (int) $rol->getKey(), null, $sistema);

        return $usuarioId;
    }

    /** Cuentas activas (de persona) con el rol global de ese nombre. */
    private function titularesActivos(string $nombreRol): int
    {
        $rolId = Role::query()->where('name', $nombreRol)->value('id');

        return $rolId === null ? 0 : User::query()->where('es_activo', true)->where('es_sistema', false)
            ->whereIn('id', DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->where('role_id', $rolId)->select('model_id'))
            ->count();
    }

    /** Cuentas activas (de personas) que pueden gestionar usuarios por un rol global o un permiso directo. */
    private function administradoresActivos(): int
    {
        $permisoId = self::idPermiso(self::PERMISO_GESTIONAR_USUARIOS);

        if ($permisoId === null) {
            return 0;
        }

        $tipo = (new User)->getMorphClass();
        $porRol = DB::table('model_has_roles as mr')->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->where('mr.model_type', $tipo)->where('rp.permission_id', $permisoId)->select('mr.model_id');
        $directos = DB::table('model_has_permissions')->where('model_type', $tipo)->where('permission_id', $permisoId)->select('model_id');

        return User::query()
            ->where('es_activo', true)
            ->where('es_sistema', false)
            ->where(fn ($q) => $q->whereIn('id', $porRol)->orWhereIn('id', $directos))
            ->count();
    }

    /** La cuenta de otra persona, bloqueada: nadie se cambia a sí mismo y la de sistema no se toca. */
    private function bloquearAjeno(int $usuarioId, User $actor): User
    {
        $usuario = User::query()->lockForUpdate()->find($usuarioId) ?? throw SeguridadRechazada::usuarioNoExiste();

        if ($usuario->es_sistema) {
            throw SeguridadRechazada::usuarioDeSistema();
        }

        if ($usuario->id === $actor->id) {
            throw SeguridadRechazada::aSiMismo();
        }

        return $usuario;
    }

    private static function exigirAmbitoDisponible(string $tipo, int $id): void
    {
        $clase = in_array($tipo, self::AMBITOS, true) ? Relation::getMorphedModel($tipo) : null;

        if ($clase === null) {
            throw SeguridadRechazada::ambitoDesconocido($tipo);
        }

        /** @var ?Model $ambito */
        $ambito = $clase::query()->lockForUpdate()->find($id);

        if ($ambito === null || $ambito->getAttribute('es_activa') === false || $ambito->getAttribute('es_activo') === false) {
            throw SeguridadRechazada::ambitoNoDisponible();
        }
    }

    /** Guarda en su propio punto de guardado y traduce las unicidades de `users` a un mensaje claro. */
    private function guardarUsuario(User $usuario): void
    {
        try {
            DB::transaction(fn (): bool => $usuario->save());
        } catch (QueryException $e) {
            // 23505: unique_violation.
            if (($e->errorInfo[0] ?? null) !== '23505') {
                throw $e;
            }

            throw match (true) {
                str_contains($e->getMessage(), 'users_persona_unique') => SeguridadRechazada::personaConCuenta(),
                str_contains($e->getMessage(), 'users_email') => SeguridadRechazada::correoRepetido($usuario->email),
                default => $e,
            };
        }
    }

    private function guardarRol(Role $rol): void
    {
        try {
            DB::transaction(fn (): bool => $rol->save());
        } catch (QueryException $e) {
            throw ($e->errorInfo[0] ?? null) === '23505' ? SeguridadRechazada::nombreRolRepetido($rol->name) : $e;
        }
    }

    private static function rol(int $id): Role
    {
        return Role::query()->find($id) ?? throw SeguridadRechazada::rolNoExiste();
    }

    private static function idPermiso(string $nombre): ?int
    {
        $id = Permission::query()->where('name', $nombre)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return list<string>
     */
    private static function nombresDePermisos(Role $rol): array
    {
        return $rol->permissions()->orderBy('name')->pluck('name')->map(fn (mixed $n): string => (string) $n)->all();
    }

    private static function nombreDelPar(IncompatibilidadPermisos $par): string
    {
        $nombres = Permission::query()->whereKey([$par->permiso_a_id, $par->permiso_b_id])->orderBy('name')->pluck('name')->all();

        return implode(' × ', $nombres);
    }

    private static function correo(string $correo): string
    {
        $correo = mb_strtolower(trim($correo));

        return filter_var($correo, FILTER_VALIDATE_EMAIL) === false ? throw SeguridadRechazada::correoInvalido() : $correo;
    }

    private static function nombre(string $nombre): string
    {
        $texto = trim($nombre);

        return $texto === '' ? throw SeguridadRechazada::faltaNombre() : $texto;
    }
}
