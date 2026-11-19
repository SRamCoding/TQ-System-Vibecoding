<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\OrganigramaRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Organigrama\DatosUnidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * M10 · Escritura del organigrama: unidades, puestos y jefaturas (plan 1.4, D5). Interno del núcleo: los demás
 * módulos escriben por las acciones registradas y leen con `jefeDe()`.
 *
 * - Ninguna unidad depende de sí misma ni de una que dependa de ella. Mover una unidad toma un candado de todo el
 *   árbol (`pg_advisory_xact_lock`) antes de revisar los ancestros: con candados de fila, dos movimientos cruzados
 *   (A bajo B y B bajo A) pasarían la revisión a la vez.
 * - El jefe es un puesto de la misma unidad y lo ocupa como máximo una persona activa. Se revisa con la fila del
 *   puesto bloqueada, aquí al designar la jefatura y en `bloquearParaOcupar()` al asignar un puesto a un usuario.
 * - Lo activo no cuelga de lo desactivado: no se desactiva una unidad con unidades o puestos activos, ni un puesto
 *   con ocupantes activos o que sea jefatura. Crear dentro de algo bloquea primero esa fila: así no se cruza con
 *   su desactivación.
 */
final class OrganigramaService
{
    private const CANDADO_ARBOL = 'nucleo.organigrama.arbol';

    /**
     * @throws OrganigramaRechazado
     */
    public function crearUnidad(DatosUnidad $datos): int
    {
        return DB::transaction(function () use ($datos): int {
            if ($datos->superiorId !== null) {
                $this->bloquearSuperiorActiva($datos->superiorId);
            }

            $unidad = new UnidadOrganizativa(['es_activa' => true]);
            $this->llenarUnidad($unidad, $datos);
            $this->guardar($unidad);

            return $unidad->id;
        });
    }

    /**
     * Reemplaza código, nombre, tipo, superior y orden. Cambiar el superior revisa que no se forme un ciclo.
     *
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function editarUnidad(int $id, DatosUnidad $datos, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $datos, $versionLeida): void {
            $superiorActual = UnidadOrganizativa::query()->whereKey($id)->value('parent_id');
            $cambiaSuperior = $datos->superiorId !== ($superiorActual === null ? null : (int) $superiorActual);

            if ($cambiaSuperior) {
                DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [self::CANDADO_ARBOL]);
            }

            $unidad = $this->bloquearUnidad($id, $versionLeida);

            if ($cambiaSuperior && $datos->superiorId !== null) {
                $superior = $this->bloquearSuperiorActiva($datos->superiorId);

                if ($this->esDescendienteOIgual($superior->id, $unidad->id)) {
                    throw OrganigramaRechazado::ciclo($unidad->nombre, $superior->nombre);
                }
            }

            $this->llenarUnidad($unidad, $datos);

            if ($unidad->isDirty()) {
                $unidad->version++;
                $this->guardar($unidad);
            }
        });
    }

    /**
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function desactivarUnidad(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $unidad = $this->bloquearUnidad($id, $versionLeida);

            if (! $unidad->es_activa) {
                throw OrganigramaRechazado::unidadYaEnEseEstado($unidad->nombre, false);
            }

            $conDependientes = UnidadOrganizativa::query()->where('parent_id', $unidad->id)->where('es_activa', true)->exists()
                || Puesto::query()->where('unidad_organizativa_id', $unidad->id)->where('es_activo', true)->exists();

            if ($conDependientes) {
                throw OrganigramaRechazado::unidadConDependientes($unidad->nombre);
            }

            $unidad->es_activa = false;
            $unidad->version++;
            $this->guardar($unidad);
        });
    }

    /**
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function activarUnidad(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $superiorId = UnidadOrganizativa::query()->whereKey($id)->value('parent_id');

            if ($superiorId !== null) {
                $this->bloquearSuperiorActiva((int) $superiorId);
            }

            $unidad = $this->bloquearUnidad($id, $versionLeida);

            if ($unidad->es_activa) {
                throw OrganigramaRechazado::unidadYaEnEseEstado($unidad->nombre, true);
            }

            $unidad->es_activa = true;
            $unidad->version++;
            $this->guardar($unidad);
        });
    }

    /**
     * Elige el puesto de jefatura de la unidad, o lo deja sin definir (`null`). El puesto es de la unidad, está
     * activo y tiene como máximo un ocupante activo.
     *
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function designarJefatura(int $unidadId, ?int $puestoId, int $versionLeida): void
    {
        DB::transaction(function () use ($unidadId, $puestoId, $versionLeida): void {
            $unidad = $this->bloquearUnidad($unidadId, $versionLeida);

            if ($puestoId !== null) {
                if (! $unidad->es_activa) {
                    throw OrganigramaRechazado::unidadInactiva();
                }

                $puesto = Puesto::query()->lockForUpdate()->find($puestoId) ?? throw OrganigramaRechazado::puestoNoExiste();

                if ($puesto->unidad_organizativa_id !== $unidad->id) {
                    throw OrganigramaRechazado::puestoDeOtraUnidad();
                }

                if (! $puesto->es_activo) {
                    throw OrganigramaRechazado::puestoInactivo($puesto->nombre);
                }

                if (self::ocupantesActivos($puesto->id)->count() > 1) {
                    throw OrganigramaRechazado::jefaturaConVariosOcupantes($puesto->nombre);
                }
            }

            $unidad->jefe_puesto_id = $puestoId;

            if ($unidad->isDirty()) {
                $unidad->version++;
                $this->guardar($unidad);
            }
        });
    }

    /**
     * @throws OrganigramaRechazado
     */
    public function crearPuesto(int $unidadId, string $nombre): int
    {
        return DB::transaction(function () use ($unidadId, $nombre): int {
            $unidad = UnidadOrganizativa::query()->lockForUpdate()->find($unidadId) ?? throw OrganigramaRechazado::unidadNoExiste();

            if (! $unidad->es_activa) {
                throw OrganigramaRechazado::unidadInactiva();
            }

            $puesto = new Puesto(['unidad_organizativa_id' => $unidad->id, 'nombre' => self::obligatorio($nombre), 'es_activo' => true]);
            $this->guardar($puesto);

            return $puesto->id;
        });
    }

    /**
     * Solo el nombre: un puesto no cambia de unidad (rompería su jefatura y la unidad de sus ocupantes). Para eso
     * se crea otro.
     *
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function editarPuesto(int $id, string $nombre, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $nombre, $versionLeida): void {
            $puesto = $this->bloquearPuesto($id, $versionLeida);
            $puesto->nombre = self::obligatorio($nombre);

            if ($puesto->isDirty()) {
                $puesto->version++;
                $this->guardar($puesto);
            }
        });
    }

    /**
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function desactivarPuesto(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $puesto = $this->bloquearPuesto($id, $versionLeida);

            if (! $puesto->es_activo) {
                throw OrganigramaRechazado::puestoYaEnEseEstado($puesto->nombre, false);
            }

            if (self::ocupantesActivos($puesto->id)->exists() || UnidadOrganizativa::query()->where('jefe_puesto_id', $puesto->id)->exists()) {
                throw OrganigramaRechazado::puestoEnUso($puesto->nombre);
            }

            $puesto->es_activo = false;
            $puesto->version++;
            $this->guardar($puesto);
        });
    }

    /**
     * @throws OrganigramaRechazado|VersionDesactualizada
     */
    public function activarPuesto(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $unidadId = Puesto::query()->whereKey($id)->value('unidad_organizativa_id');
            $unidad = UnidadOrganizativa::query()->lockForUpdate()->find($unidadId);
            $puesto = $this->bloquearPuesto($id, $versionLeida);

            if ($puesto->es_activo) {
                throw OrganigramaRechazado::puestoYaEnEseEstado($puesto->nombre, true);
            }

            if ($unidad === null || ! $unidad->es_activa) {
                throw OrganigramaRechazado::unidadInactiva();
            }

            $puesto->es_activo = true;
            $puesto->version++;
            $this->guardar($puesto);
        });
    }

    /**
     * Para asignar un puesto a un usuario (SeguridadService), dentro de su transacción: bloquea el puesto y exige que
     * esté activo y, si es jefatura de alguna unidad, que nadie más activo lo ocupe.
     *
     * @throws OrganigramaRechazado
     */
    public function bloquearParaOcupar(int $puestoId, ?int $usuarioId): Puesto
    {
        $puesto = Puesto::query()->lockForUpdate()->find($puestoId) ?? throw OrganigramaRechazado::puestoNoExiste();

        if (! $puesto->es_activo) {
            throw OrganigramaRechazado::puestoInactivo($puesto->nombre);
        }

        $esJefatura = UnidadOrganizativa::query()->where('jefe_puesto_id', $puesto->id)->exists();
        $otros = self::ocupantesActivos($puesto->id)->when($usuarioId !== null, fn ($q) => $q->whereKeyNot($usuarioId));

        if ($esJefatura && $otros->exists()) {
            throw OrganigramaRechazado::jefaturaOcupada($puesto->nombre);
        }

        return $puesto;
    }

    /**
     * Quien ocupa hoy el puesto de jefatura de la unidad; nulo si no está definido o está vacante. Para la 1.7: si
     * es nulo, la aprobación sube por `parent_id`.
     */
    public function jefeDe(UnidadOrganizativa|int $unidad): ?User
    {
        $puestoId = $unidad instanceof UnidadOrganizativa
            ? $unidad->jefe_puesto_id
            : UnidadOrganizativa::query()->whereKey($unidad)->value('jefe_puesto_id');

        return $puestoId === null ? null : self::ocupantesActivos((int) $puestoId)->orderBy('id')->first();
    }

    /**
     * @return Builder<User>
     */
    private static function ocupantesActivos(int $puestoId): Builder
    {
        return User::query()->where('puesto_id', $puestoId)->where('es_activo', true);
    }

    /** Si `$candidata` es `$unidad` o cuelga de ella, a cualquier profundidad. */
    private function esDescendienteOIgual(int $candidata, int $unidad): bool
    {
        $fila = DB::selectOne(<<<'SQL'
            WITH RECURSIVE ancestros(id, parent_id) AS (
                SELECT id, parent_id FROM unidades_organizativas WHERE id = ?
                UNION
                SELECT u.id, u.parent_id FROM unidades_organizativas u JOIN ancestros a ON u.id = a.parent_id
            )
            SELECT EXISTS (SELECT 1 FROM ancestros WHERE id = ?) AS hay
            SQL, [$candidata, $unidad]);

        return (bool) $fila->hay;
    }

    private function llenarUnidad(UnidadOrganizativa $unidad, DatosUnidad $datos): void
    {
        $unidad->fill([
            'codigo' => mb_strtoupper(self::obligatorio($datos->codigo)),
            'nombre' => self::obligatorio($datos->nombre),
            'tipo' => $datos->tipo,
            'parent_id' => $datos->superiorId,
            'orden' => $datos->orden,
        ]);
    }

    private function bloquearSuperiorActiva(int $id): UnidadOrganizativa
    {
        $superior = UnidadOrganizativa::query()->lockForUpdate()->find($id) ?? throw OrganigramaRechazado::unidadNoExiste();

        if (! $superior->es_activa) {
            throw OrganigramaRechazado::superiorInactiva();
        }

        return $superior;
    }

    private function bloquearUnidad(int $id, int $versionLeida): UnidadOrganizativa
    {
        $unidad = UnidadOrganizativa::query()->lockForUpdate()->find($id) ?? throw OrganigramaRechazado::unidadNoExiste();

        if ($unidad->version !== $versionLeida) {
            throw VersionDesactualizada::de("la unidad «{$unidad->nombre}»");
        }

        return $unidad;
    }

    private function bloquearPuesto(int $id, int $versionLeida): Puesto
    {
        $puesto = Puesto::query()->lockForUpdate()->find($id) ?? throw OrganigramaRechazado::puestoNoExiste();

        if ($puesto->version !== $versionLeida) {
            throw VersionDesactualizada::de("el puesto «{$puesto->nombre}»");
        }

        return $puesto;
    }

    /** Guarda en su propio punto de guardado y traduce las unicidades a un mensaje claro. */
    private function guardar(UnidadOrganizativa|Puesto $modelo): void
    {
        try {
            DB::transaction(fn (): bool => $modelo->save());
        } catch (QueryException $e) {
            // 23505: unique_violation.
            if (($e->errorInfo[0] ?? null) !== '23505') {
                throw $e;
            }

            throw match (true) {
                str_contains($e->getMessage(), 'unidades_organizativas_codigo_unique') => OrganigramaRechazado::codigoRepetido(self::atributo($modelo, 'codigo')),
                str_contains($e->getMessage(), 'unidades_organizativas_nombre_unique') => OrganigramaRechazado::nombreUnidadRepetido(self::atributo($modelo, 'nombre')),
                str_contains($e->getMessage(), 'puestos_nombre_unique') => OrganigramaRechazado::nombrePuestoRepetido(self::atributo($modelo, 'nombre')),
                default => $e,
            };
        }
    }

    private static function atributo(Model $modelo, string $campo): string
    {
        return (string) $modelo->getAttribute($campo);
    }

    private static function obligatorio(string $valor): string
    {
        $texto = trim($valor);

        return $texto === '' ? throw OrganigramaRechazado::faltanDatos() : $texto;
    }
}
