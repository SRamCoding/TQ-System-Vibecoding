<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Alertas\TextoAlerta;
use App\Modules\Nucleo\Excepciones\AlertaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\AlertaTipo;
use App\Modules\Nucleo\Models\AlertaTipoRol;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * M13 · El catálogo único de alertas (plan 1.10). Una alerta es un aviso que el sistema manda solo: se emite una sola
 * vez por hecho (`alertas_emitidas.clave`, aunque el job se repita) y llega por `NotificacionesService` a toda persona
 * activa con alguno de los roles de su tipo, más las que sume quien la emite (D2). Si ningún rol tiene a nadie activo,
 * llega al administrador, para que no se pierda. Sus textos son los que se editaron o, si no, los de fábrica, siempre con
 * sus datos reales (D5). Apagada, no se emite; una obligatoria no se apaga.
 */
final readonly class AlertasService
{
    public function __construct(
        private RegistroAlertas $registro,
        private NotificacionesService $notificaciones,
    ) {}

    /**
     * Emite el hecho una sola vez. Devuelve si se emitió ahora (falso si ya se había emitido o la alerta está apagada).
     *
     * @param  string  $hecho  lo que hace único el hecho dentro de su alerta: `2026-10-27`, `efecto:15`
     * @param  array<string, string|int>  $datos  el valor real de cada dato de la alerta
     * @param  iterable<User>  $ademas  personas puntuales que también la reciben (quien hizo el cambio de estado)
     */
    public function emitir(string $clave, string $hecho, array $datos, ?Model $entidad = null, ?string $enlace = null, iterable $ademas = []): bool
    {
        $alerta = $this->registro->alerta($clave);
        $faltan = array_diff(array_keys($alerta->datos), array_keys($datos));
        $sobran = array_diff(array_keys($datos), array_keys($alerta->datos));

        if ($faltan !== [] || $sobran !== []) {
            throw new InvalidArgumentException("La alerta «{$clave}» se emite con sus datos exactos: ".implode(', ', array_keys($alerta->datos)).'.');
        }

        if (trim($hecho) === '' || mb_strlen($hecho) > 110) {
            throw new InvalidArgumentException("El hecho de la alerta «{$clave}» va sin vacíos y hasta 110 caracteres.");
        }

        $tipo = $this->tipo($alerta);

        if (! $tipo->es_activa && ! $alerta->obligatoria) {
            return false;
        }

        return DB::transaction(function () use ($alerta, $tipo, $hecho, $datos, $entidad, $enlace, $ademas): bool {
            // La base decide si ya se emitió (clave única): un reintento, una revisión repetida o dos a la vez no la
            // vuelven a mandar.
            $emitida = AlertaEmitida::query()->createOrFirst(['clave' => "{$alerta->clave}:{$hecho}"], [
                'alerta_tipo_id' => $tipo->id,
                'entidad_type' => $entidad?->getMorphClass(),
                'entidad_id' => $entidad?->getKey(),
            ]);

            if (! $emitida->wasRecentlyCreated) {
                return false;
            }

            // Plan 2.2, D6: una alerta con destinatario propio llega por sus roles sin el respaldo del administrador; si
            // tampoco la persona puntual puede recibirla (inactiva), entra el respaldo de siempre.
            $personas = ($alerta->conDestinatarioPropio() ? $this->conRoles($this->rolesDe($tipo)) : $this->destinatarios($tipo))->keyBy('id');

            foreach ($ademas as $persona) {
                if ($persona->es_activo && ! $persona->es_sistema) {
                    $personas->put($persona->id, $persona);
                }
            }

            if ($personas->isEmpty()) {
                $personas = $this->destinatarios($tipo)->keyBy('id');
            }

            $this->notificaciones->avisar(
                $alerta->clave,
                $personas->values(),
                TextoAlerta::llenar($tipo->asunto ?? $alerta->asunto, $datos),
                TextoAlerta::llenar($tipo->texto ?? $alerta->texto, $datos),
                $enlace,
                $hecho,
            );

            return true;
        });
    }

    /**
     * Deja el hecho como ya emitido sin avisar a nadie (plan 2.2, D6): cuando un hecho mayor tapa a uno menor (la partida
     * pasa del 80 % a excedida de una vez), el menor no debe salir después. Devuelve si lo registró ahora.
     */
    public function darPorEmitida(string $clave, string $hecho, ?Model $entidad = null): bool
    {
        $tipo = $this->tipo($this->registro->alerta($clave));

        return AlertaEmitida::query()->createOrFirst(['clave' => "{$clave}:{$hecho}"], [
            'alerta_tipo_id' => $tipo->id,
            'entidad_type' => $entidad?->getMorphClass(),
            'entidad_id' => $entidad?->getKey(),
        ])->wasRecentlyCreated;
    }

    /**
     * Guarda lo editable de un tipo de alerta desde Configuración → Alertas (plan 1.10, D2, D5 y D6): sus roles (al
     * menos uno), si está activa (una obligatoria no se apaga), sus días de anticipación (solo si avisa con
     * anticipación) y sus textos, que deben traer cada dato y ningún marcador que no exista. Un texto igual al de
     * fábrica se guarda vacío: si el de fábrica cambia, lo sigue. Con bloqueo optimista.
     *
     * @param  list<int>  $rolIds
     *
     * @throws AlertaRechazada|VersionDesactualizada
     */
    public function configurar(string $clave, array $rolIds, bool $activa, ?string $anticipacion, string $asunto, string $texto, int $versionLeida): void
    {
        $alerta = $this->registro->alerta($clave);
        $asunto = trim($asunto);
        $texto = trim($texto);
        $anticipacion = $anticipacion === null || trim($anticipacion) === '' ? null : str_replace(' ', '', $anticipacion);
        $rolIds = array_values(array_unique($rolIds));

        if ($rolIds === [] && ! $alerta->conDestinatarioPropio()) {
            throw AlertaRechazada::sinRoles();
        }

        if (Role::query()->whereKey($rolIds)->count() !== count($rolIds)) {
            throw AlertaRechazada::rolNoExiste();
        }

        if (! $activa && $alerta->obligatoria) {
            throw AlertaRechazada::obligatoria($alerta->nombre);
        }

        if ($anticipacion !== null && $alerta->anticipacion === null) {
            throw AlertaRechazada::sinAnticipacion();
        }

        if ($anticipacion !== null && ! TextoAlerta::anticipacionValida($anticipacion)) {
            throw AlertaRechazada::anticipacionInvalida();
        }

        foreach (TextoAlerta::faltantes($asunto, $texto, array_keys($alerta->datos)) as $dato) {
            throw AlertaRechazada::faltaDato($alerta->datos[$dato]);
        }

        foreach (TextoAlerta::desconocidos($asunto, $texto, array_keys($alerta->datos)) as $marcador) {
            throw AlertaRechazada::datoDesconocido($marcador, array_keys($alerta->datos));
        }

        $id = $this->tipo($alerta)->id;

        DB::transaction(function () use ($alerta, $id, $rolIds, $activa, $anticipacion, $asunto, $texto, $versionLeida): void {
            $tipo = AlertaTipo::query()->lockForUpdate()->findOrFail($id);

            if ($tipo->version !== $versionLeida) {
                throw VersionDesactualizada::de("la alerta «{$alerta->nombre}»");
            }

            $actuales = $tipo->roles()->pluck('rol_id')->map(fn (mixed $rol): int => (int) $rol)->all();
            $tipo->roles()->whereNotIn('rol_id', $rolIds)->delete();

            foreach (array_diff($rolIds, $actuales) as $rolId) {
                AlertaTipoRol::query()->create(['alerta_tipo_id' => $tipo->id, 'rol_id' => $rolId]);
            }

            $tipo->fill([
                'es_activa' => $activa,
                'anticipacion_dias' => $anticipacion,
                'asunto' => $asunto === $alerta->asunto ? null : $asunto,
                'texto' => $texto === $alerta->texto ? null : $texto,
            ]);
            $tipo->version++;
            $tipo->save();
        });
    }

    /**
     * Si ninguna persona activa tiene los roles de la alerta: entonces le llega al administrador, y la pantalla lo
     * marca para que se asigne a alguien (D2).
     */
    public function sinDestinatariosPropios(AlertaTipo $tipo): bool
    {
        // Plan 2.2, D6: la que llega a su persona puntual siempre tiene a quién.
        if (($this->registro->alertas()[$tipo->codigo] ?? null)?->conDestinatarioPropio() === true) {
            return false;
        }

        return $this->conRoles($tipo->roles()->pluck('rol_id')->map(fn (mixed $id): int => (int) $id)->all())->isEmpty();
    }

    /**
     * De estas alertas, las claves de las que hoy no le llegan a nadie por sus roles (les llega al administrador, D2).
     * Todas de una vez (plan 1.12, prueba de carga: la pantalla de Alertas hacía tres consultas por alerta): sus tipos con
     * sus roles en dos consultas y, en una sola, qué roles tienen a alguna persona activa.
     *
     * @param  iterable<DefinicionAlerta>  $alertas
     * @return list<string>
     */
    public function sinDestinatariosPropiosDe(iterable $alertas): array
    {
        $alertas = collect($alertas);
        $tipos = AlertaTipo::query()->whereIn('codigo', $alertas->map(fn (DefinicionAlerta $alerta): string => $alerta->clave)->all())->with('roles')->get()->keyBy('codigo');

        foreach ($alertas as $alerta) {
            // Una alerta recién declarada todavía no tiene fila: la crea `tipo()`, con sus roles de fábrica.
            if (! $tipos->has($alerta->clave)) {
                $tipos[$alerta->clave] = $this->tipo($alerta)->load('roles');
            }
        }

        $conPersonas = DB::table('model_has_roles')
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', User::query()->where('es_activo', true)->where('es_sistema', false)->select('id'))
            ->distinct()
            ->pluck('role_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $alertas
            // Una con destinatario propio siempre llega a su persona puntual (plan 2.2, D6).
            ->reject(fn (DefinicionAlerta $alerta): bool => $alerta->conDestinatarioPropio())
            ->filter(fn (DefinicionAlerta $alerta): bool => array_intersect($tipos[$alerta->clave]->roles->pluck('rol_id')->map(fn (mixed $id): int => (int) $id)->all(), $conPersonas) === [])
            ->map(fn (DefinicionAlerta $alerta): string => $alerta->clave)
            ->values()
            ->all();
    }

    /**
     * Las personas activas con alguno de los roles de la alerta; si no hay ninguna, los administradores activos.
     *
     * @return Collection<int, User>
     */
    public function destinatarios(AlertaTipo $tipo): Collection
    {
        $personas = $this->conRoles($tipo->roles()->pluck('rol_id')->map(fn (mixed $id): int => (int) $id)->all());

        if ($personas->isNotEmpty()) {
            return $personas;
        }

        return $this->conRoles(Role::query()->where('name', SeguridadService::ROL_ADMINISTRADOR)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Los días antes del vencimiento en que avisa, de mayor a menor; vacío si la alerta no avisa con anticipación.
     * Plan 1.10, D6: la persona todavía no ajusta los suyos; cuando pueda (`alerta_preferencias`), se resuelve aquí.
     *
     * @return list<int>
     */
    public function anticipacion(string $clave, ?User $persona = null): array
    {
        $dias = $this->tipo($this->registro->alerta($clave))->anticipacion_dias;

        if ($dias === null) {
            return [];
        }

        $lista = array_map('intval', explode(',', $dias));
        rsort($lista);

        return $lista;
    }

    /**
     * La revisión diaria (D4): corre cada fuente registrada. Una que falla no frena a las otras; queda en el registro,
     * solo con su clase y la del error (regla 10: el mensaje podría llevar datos).
     */
    public function revisarDelDia(CarbonImmutable $hoy): void
    {
        foreach ($this->registro->fuentes() as $fuente) {
            try {
                app($fuente)->revisar($hoy);
            } catch (Throwable $error) {
                Log::error('Una revisión de alertas falló.', ['fuente' => $fuente, 'error' => $error::class]);
            }
        }
    }

    /**
     * Crea en `alerta_tipos` cada tipo declarado que falte, con sus roles por defecto. No toca los que ya existen: lo
     * editado se queda. Lo corre el seeder del núcleo en cada despliegue.
     */
    public function sincronizar(): void
    {
        foreach ($this->registro->alertas() as $alerta) {
            $this->tipo($alerta);
        }
    }

    /** La fila editable del tipo; si todavía no existe, la crea con sus roles por defecto. */
    public function tipo(DefinicionAlerta $alerta): AlertaTipo
    {
        $existente = AlertaTipo::query()->where('codigo', $alerta->clave)->first();

        if ($existente !== null) {
            return $existente;
        }

        return DB::transaction(function () use ($alerta): AlertaTipo {
            $tipo = AlertaTipo::query()->createOrFirst(['codigo' => $alerta->clave], ['es_activa' => true, 'anticipacion_dias' => $alerta->anticipacion]);

            if ($tipo->wasRecentlyCreated) {
                // Siempre al menos un rol (D2): si los de fábrica todavía no existen, el administrador. Salvo la que llega a
                // su persona puntual (plan 2.2, D6), que puede quedar sin roles.
                $rolIds = Role::query()->whereIn('name', $alerta->rolesPorDefecto)->pluck('id');
                $rolIds = $rolIds->isNotEmpty() || $alerta->conDestinatarioPropio() ? $rolIds : Role::query()->where('name', SeguridadService::ROL_ADMINISTRADOR)->pluck('id');

                foreach ($rolIds as $rolId) {
                    AlertaTipoRol::query()->create(['alerta_tipo_id' => $tipo->id, 'rol_id' => (int) $rolId]);
                }
            }

            return $tipo;
        });
    }

    /** @return list<int> */
    private function rolesDe(AlertaTipo $tipo): array
    {
        return $tipo->roles()->pluck('rol_id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $rolIds
     * @return Collection<int, User>
     */
    private function conRoles(array $rolIds): Collection
    {
        if ($rolIds === []) {
            return new Collection;
        }

        return User::query()
            ->where('es_activo', true)
            ->where('es_sistema', false)
            ->whereIn('id', DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('role_id', $rolIds)->select('model_id'))
            ->orderBy('id')
            ->get();
    }
}
