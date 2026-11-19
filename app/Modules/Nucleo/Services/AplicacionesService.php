<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Aplicaciones\AplicacionDelPanel;
use App\Modules\Nucleo\Aplicaciones\DatosAplicacionPropia;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Excepciones\AplicacionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\AplicacionEnEscritorio;
use App\Modules\Nucleo\Models\AplicacionOculta;
use App\Modules\Nucleo\Models\AplicacionPrincipal;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\OrdenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Models\UsoAplicacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Qué aplicaciones ve cada persona en el panel de aplicaciones (plan 1.4p). Único lugar con la regla: las de Techquk
 * activas que no ocultó, más sus propias. El muelle son sus 3 principales, que elige ella (plan 1.4x, D1 y D2); sin
 * elegir, las 3 primeras del orden de administración. El uso se sigue contando, pero ya no decide nada (D3).
 */
final class AplicacionesService
{
    /** Cuántas caben en el muelle (D2). */
    public const int DEL_MUELLE = 3;

    public function __construct(
        private readonly ContextoAuditoria $auditoria,
        private readonly IconosAplicacionService $iconos,
    ) {}

    /**
     * Las 3 del muelle: sus principales, en su orden (plan 1.4x, D1).
     *
     * @return list<AplicacionDelPanel>
     */
    public function delMuelle(User $usuario): array
    {
        return $this->delPanel($usuario)['muelle'];
    }

    /**
     * El muelle (sus 3 principales) y el resto de la cuadrícula de una sola vez, para la pantalla. Las principales no se
     * repiten abajo.
     *
     * @return array{muelle: list<AplicacionDelPanel>, cuadricula: array{techquk: list<AplicacionDelPanel>, propias: list<AplicacionDelPanel>}}
     */
    public function delPanel(User $usuario): array
    {
        $cuadricula = $this->cuadricula($usuario, $this->usos($usuario));
        $principales = $this->principales($usuario, $cuadricula);
        $arriba = array_map(fn (AplicacionDelPanel $aplicacion): string => self::clave($aplicacion->origen, $aplicacion->id), $principales);
        $resto = fn (array $lista): array => array_values(array_filter(
            $lista,
            fn (AplicacionDelPanel $aplicacion): bool => ! in_array(self::clave($aplicacion->origen, $aplicacion->id), $arriba, true),
        ));

        return [
            'muelle' => $principales,
            'cuadricula' => ['techquk' => $resto($cuadricula['techquk']), 'propias' => $resto($cuadricula['propias'])],
        ];
    }

    /**
     * La persona deja una aplicación de su panel en una de sus 3 principales (plan 1.4x, D2). Si ya era principal en otra
     * posición, intercambian lugares; si no, reemplaza a la que estaba. Se guardan las 3 tal como quedan, así una de
     * fábrica que no eligió no cambia sola si administración reordena después. Dos cambios a la vez de la misma persona
     * se esperan (candado por persona); la base impide repetir una posición o una aplicación.
     *
     * @throws AplicacionRechazada
     */
    public function ponerPrincipal(User $usuario, int $posicion, OrigenAplicacion $origen, int $id): void
    {
        if ($posicion < 1 || $posicion > self::DEL_MUELLE) {
            throw AplicacionRechazada::posicionInvalida();
        }

        DB::transaction(function () use ($usuario, $posicion, $origen, $id): void {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['aplicaciones-principales:'.$usuario->id]);

            if (! $this->enSuPanel($usuario, $origen, $id)) {
                throw AplicacionRechazada::noDisponible();
            }

            $lugares = array_pad($this->principales($usuario, $this->cuadricula($usuario, $this->usos($usuario))), self::DEL_MUELLE, null);
            $claves = array_map(fn (?AplicacionDelPanel $aplicacion): ?string => $aplicacion === null ? null : self::clave($aplicacion->origen, $aplicacion->id), $lugares);
            $elegida = ['origen' => $origen, 'id' => $id];
            $nuevos = array_map(fn (?AplicacionDelPanel $aplicacion): ?array => $aplicacion === null ? null : ['origen' => $aplicacion->origen, 'id' => $aplicacion->id], $lugares);
            $estaba = array_search(self::clave($origen, $id), $claves, true);

            if ($estaba !== false) {
                $nuevos[$estaba] = $nuevos[$posicion - 1];
            }

            $nuevos[$posicion - 1] = $elegida;

            AplicacionPrincipal::query()->where('user_id', $usuario->id)->delete();

            try {
                foreach ($nuevos as $indice => $lugar) {
                    if ($lugar !== null) {
                        AplicacionPrincipal::query()->create([
                            'user_id' => $usuario->id,
                            'posicion' => $indice + 1,
                            'aplicacion_id' => $lugar['origen'] === OrigenAplicacion::Techquk ? $lugar['id'] : null,
                            'aplicacion_propia_id' => $lugar['origen'] === OrigenAplicacion::Propia ? $lugar['id'] : null,
                        ]);
                    }
                }
            } catch (QueryException $e) {
                // 23503: foreign_key_violation. Quitó la propia en otra pestaña entre la revisión y el INSERT (el candado
                // ordena solo los cambios de principales, no los de quitar).
                throw ($e->errorInfo[0] ?? null) === '23503' ? AplicacionRechazada::noDisponible() : $e;
            }
        });
    }

    /**
     * Todas las de la cuadrícula (D2): arriba las de Techquk en el orden de administración, abajo las propias por nombre.
     *
     * @return array{techquk: list<AplicacionDelPanel>, propias: list<AplicacionDelPanel>}
     */
    public function deLaCuadricula(User $usuario): array
    {
        return $this->cuadricula($usuario, $this->usos($usuario));
    }

    /**
     * Suma un uso (D5): la persona abrió la aplicación desde el panel. Dos a la vez no se pisan (ON CONFLICT). Solo de
     * una que está en su panel.
     */
    public function registrarUso(User $usuario, OrigenAplicacion $origen, int $id): void
    {
        $columna = match ($origen) {
            OrigenAplicacion::Techquk => 'aplicacion_id',
            OrigenAplicacion::Propia => 'aplicacion_propia_id',
        };

        if (! $this->enSuPanel($usuario, $origen, $id)) {
            throw AplicacionRechazada::noDisponible();
        }

        try {
            DB::insert(
                "INSERT INTO usos_aplicacion (user_id, {$columna}, usos, ultimo_uso_at, created_by, created_at)
                 VALUES (?, ?, 1, clock_timestamp(), ?, now())
                 ON CONFLICT (user_id, {$columna})
                 DO UPDATE SET usos = usos_aplicacion.usos + 1, ultimo_uso_at = clock_timestamp(), updated_by = EXCLUDED.created_by, updated_at = now()",
                [$usuario->id, $id, $this->auditoria->idActor()],
            );
        } catch (QueryException $e) {
            // 23503: foreign_key_violation. La propia se quitó entre la revisión y el INSERT (otra pestaña).
            throw ($e->errorInfo[0] ?? null) === '23503' ? AplicacionRechazada::noDisponible() : $e;
        }
    }

    /**
     * La persona suma una aplicación a su panel (D7). Solo la ve ella. Devuelve su id.
     *
     * @throws AplicacionRechazada
     */
    public function agregarPropia(User $usuario, DatosAplicacionPropia $datos): int
    {
        $propia = new AplicacionPropia(['user_id' => $usuario->id]);
        $this->llenar($propia, $datos);
        $this->guardar($propia);

        return $propia->id;
    }

    /**
     * @throws AplicacionRechazada|VersionDesactualizada
     */
    public function cambiarPropia(User $usuario, int $id, DatosAplicacionPropia $datos, int $versionLeida): void
    {
        DB::transaction(function () use ($usuario, $id, $datos, $versionLeida): void {
            $propia = $this->bloquear($usuario, $id, $versionLeida);
            $this->llenar($propia, $datos);

            if ($propia->isDirty()) {
                $propia->version++;
                $this->guardar($propia);
            }
        });
    }

    /**
     * Su conteo de uso se borra con ella (la base lo hace).
     *
     * @throws AplicacionRechazada|VersionDesactualizada
     */
    public function quitarPropia(User $usuario, int $id, int $versionLeida): void
    {
        DB::transaction(fn (): ?bool => $this->bloquear($usuario, $id, $versionLeida)->delete());
    }

    /**
     * La persona saca de su panel una de Techquk que no usa (D7). Sin versión: la última escritura gana, como los módulos
     * de la barra. Ocultar una ya oculta no hace nada.
     *
     * @throws AplicacionRechazada
     */
    public function ocultar(User $usuario, int $aplicacionId): void
    {
        if (! Aplicacion::query()->activos()->whereKey($aplicacionId)->exists()) {
            throw AplicacionRechazada::noDisponible();
        }

        // Atómico: un doble clic o dos pestañas no chocan con la unicidad (la segunda no hace nada).
        DB::insert(
            'INSERT INTO aplicaciones_ocultas (user_id, aplicacion_id, created_by, created_at) VALUES (?, ?, ?, now())
             ON CONFLICT (user_id, aplicacion_id) DO NOTHING',
            [$usuario->id, $aplicacionId, $this->auditoria->idActor()],
        );
    }

    /**
     * Las de Techquk activas que la persona ocultó, en el orden de administración: el panel las ofrece para volver a
     * mostrarlas.
     *
     * @return list<AplicacionDelPanel>
     */
    public function ocultas(User $usuario): array
    {
        $ocultas = Aplicacion::query()
            ->activos()
            ->whereIn('id', AplicacionOculta::query()->where('user_id', $usuario->id)->select('aplicacion_id'))
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        $imagenes = $this->iconos->imagenes($this->iconosSubidos($ocultas));

        return $ocultas
            ->map(fn (Aplicacion $aplicacion): AplicacionDelPanel => new AplicacionDelPanel(
                OrigenAplicacion::Techquk, $aplicacion->id, $aplicacion->nombre, $aplicacion->enlace, $aplicacion->icono, 0,
                imagen: $imagenes[$aplicacion->icono_adjunto_id] ?? null,
            ))
            ->values()
            ->all();
    }

    /** La vuelve a mostrar. Mostrar una que no estaba oculta no hace nada. */
    public function mostrar(User $usuario, int $aplicacionId): void
    {
        AplicacionOculta::query()->where('user_id', $usuario->id)->where('aplicacion_id', $aplicacionId)->delete();
    }

    /**
     * Los ids de los íconos subidos de las que usan «propio» (plan 1.4z, D9).
     *
     * @param  Collection<int, Aplicacion>  $aplicaciones
     * @return list<int>
     */
    private function iconosSubidos(Collection $aplicaciones): array
    {
        return $aplicaciones->filter(fn (Aplicacion $aplicacion): bool => $aplicacion->icono === IconoAplicacion::Propio && $aplicacion->icono_adjunto_id !== null)
            ->map(fn (Aplicacion $aplicacion): int => (int) $aplicacion->icono_adjunto_id)->values()->all();
    }

    /**
     * La persona suelta una aplicación sobre otra de su misma sección y cambian de lugar (plan 1.4z, D10). Se guarda el
     * orden completo de esa sección tal como queda; solo cambia lo que ve ella. Dos cambios a la vez de la misma persona
     * se esperan (candado por persona); la base impide repetir una posición en la sección.
     *
     * @throws AplicacionRechazada
     */
    public function mover(User $usuario, OrigenAplicacion $origen, int $id, int $sobreId): void
    {
        if ($id === $sobreId) {
            return;
        }

        DB::transaction(function () use ($usuario, $origen, $id, $sobreId): void {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['aplicaciones-orden:'.$usuario->id]);

            if (! $this->enSuPanel($usuario, $origen, $id) || ! $this->enSuPanel($usuario, $origen, $sobreId)) {
                throw AplicacionRechazada::noDisponible();
            }

            $seccion = $this->cuadricula($usuario, $this->usos($usuario))[$origen === OrigenAplicacion::Techquk ? 'techquk' : 'propias'];
            $ids = array_map(fn (AplicacionDelPanel $aplicacion): int => $aplicacion->id, $seccion);
            [$a, $b] = [array_search($id, $ids, true), array_search($sobreId, $ids, true)];

            // Revisión de arquitectura: si una dejó la sección en medio (la ocultó en otra pestaña), no se intercambia
            // con la primera por error.
            if ($a === false || $b === false) {
                throw AplicacionRechazada::noDisponible();
            }
            [$ids[$a], $ids[$b]] = [$ids[$b], $ids[$a]];
            $columna = $origen === OrigenAplicacion::Techquk ? 'aplicacion_id' : 'aplicacion_propia_id';

            OrdenAplicacion::query()->where('user_id', $usuario->id)->whereNotNull($columna)->delete();

            try {
                foreach ($ids as $indice => $aplicacionId) {
                    OrdenAplicacion::query()->create(['user_id' => $usuario->id, $columna => $aplicacionId, 'posicion' => $indice + 1]);
                }
            } catch (QueryException $e) {
                // 23503: la propia se quitó en otra pestaña entre la revisión y el INSERT.
                throw ($e->errorInfo[0] ?? null) === '23503' ? AplicacionRechazada::noDisponible() : $e;
            }
        });
    }

    /**
     * La persona elige si abre una aplicación en su programa de escritorio o en la web (plan 1.4z, D6). Solo una que tiene
     * programa; elegir dos veces lo mismo no hace nada.
     *
     * @throws AplicacionRechazada
     */
    public function abrirEn(User $usuario, OrigenAplicacion $origen, int $id, bool $enEscritorio): void
    {
        if (! $this->enSuPanel($usuario, $origen, $id)) {
            throw AplicacionRechazada::noDisponible();
        }

        $programa = $origen === OrigenAplicacion::Techquk ? Aplicacion::query()->whereKey($id)->value('escritorio') : AplicacionPropia::query()->whereKey($id)->value('escritorio');

        if ($enEscritorio && $programa === null) {
            throw AplicacionRechazada::sinEscritorio();
        }

        $columna = $origen === OrigenAplicacion::Techquk ? 'aplicacion_id' : 'aplicacion_propia_id';

        if (! $enEscritorio) {
            AplicacionEnEscritorio::query()->where('user_id', $usuario->id)->where($columna, $id)->delete();

            return;
        }

        try {
            // Atómico: un doble clic o dos pestañas no chocan con la unicidad (la segunda no hace nada).
            DB::insert(
                "INSERT INTO aplicaciones_en_escritorio (user_id, {$columna}, created_by, created_at) VALUES (?, ?, ?, now())
                 ON CONFLICT (user_id, {$columna}) DO NOTHING",
                [$usuario->id, $id, $this->auditoria->idActor()],
            );
        } catch (QueryException $e) {
            throw ($e->errorInfo[0] ?? null) === '23503' ? AplicacionRechazada::noDisponible() : $e;
        }
    }

    /** Una de Techquk activa que no ocultó, o una propia suya. */
    private function enSuPanel(User $usuario, OrigenAplicacion $origen, int $id): bool
    {
        return match ($origen) {
            OrigenAplicacion::Techquk => Aplicacion::query()->activos()->whereKey($id)
                ->whereNotIn('id', AplicacionOculta::query()->where('user_id', $usuario->id)->select('aplicacion_id'))->exists(),
            OrigenAplicacion::Propia => AplicacionPropia::query()->whereKey($id)->where('user_id', $usuario->id)->exists(),
        };
    }

    /**
     * Sus 3 principales en su orden (D1). Cada posición guardada que sigue en su panel queda en su lugar; las que no (la
     * ocultó, la quitó, administración la desactivó) y las que nunca eligió las toma la siguiente del orden de la
     * cuadrícula: Techquk en el orden de administración y luego las propias.
     *
     * @param  array{techquk: list<AplicacionDelPanel>, propias: list<AplicacionDelPanel>}  $cuadricula
     * @return list<AplicacionDelPanel>
     */
    private function principales(User $usuario, array $cuadricula): array
    {
        $todas = [];

        foreach ([...$cuadricula['techquk'], ...$cuadricula['propias']] as $aplicacion) {
            $todas[self::clave($aplicacion->origen, $aplicacion->id)] = $aplicacion;
        }

        $lugares = array_fill(1, self::DEL_MUELLE, null);

        foreach (AplicacionPrincipal::query()->where('user_id', $usuario->id)->get() as $fila) {
            $clave = $fila->aplicacion_id !== null
                ? self::clave(OrigenAplicacion::Techquk, $fila->aplicacion_id)
                : self::clave(OrigenAplicacion::Propia, (int) $fila->aplicacion_propia_id);
            $lugares[$fila->posicion] = $todas[$clave] ?? null;
        }

        $usadas = array_map(fn (AplicacionDelPanel $aplicacion): string => self::clave($aplicacion->origen, $aplicacion->id), array_filter($lugares));
        $siguientes = array_values(array_diff_key($todas, array_flip($usadas)));

        foreach ($lugares as $posicion => $aplicacion) {
            if ($aplicacion === null && $siguientes !== []) {
                $lugares[$posicion] = array_shift($siguientes);
            }
        }

        return array_values(array_filter($lugares));
    }

    /**
     * @return Collection<int, UsoAplicacion>
     */
    private function usos(User $usuario): Collection
    {
        return UsoAplicacion::query()->where('user_id', $usuario->id)->orderByDesc('ultimo_uso_at')->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, UsoAplicacion>  $usos
     * @return array{techquk: list<AplicacionDelPanel>, propias: list<AplicacionDelPanel>}
     */
    private function cuadricula(User $usuario, Collection $usos): array
    {
        $deTechquk = $usos->whereNotNull('aplicacion_id')->pluck('usos', 'aplicacion_id');
        $dePropias = $usos->whereNotNull('aplicacion_propia_id')->pluck('usos', 'aplicacion_propia_id');

        $deTechqukActivas = Aplicacion::query()
            ->activos()
            ->whereNotIn('id', AplicacionOculta::query()->where('user_id', $usuario->id)->select('aplicacion_id'))
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        $imagenes = $this->iconos->imagenes($this->iconosSubidos($deTechqukActivas));
        $techquk = $deTechqukActivas
            ->map(fn (Aplicacion $aplicacion): AplicacionDelPanel => new AplicacionDelPanel(
                OrigenAplicacion::Techquk, $aplicacion->id, $aplicacion->nombre, $aplicacion->enlace, $aplicacion->icono,
                (int) ($deTechquk[$aplicacion->id] ?? 0), imagen: $imagenes[$aplicacion->icono_adjunto_id] ?? null,
                escritorio: $aplicacion->escritorio,
            ));

        $enEscritorio = AplicacionEnEscritorio::query()->where('user_id', $usuario->id)->get();
        $techqukEscritorio = $enEscritorio->whereNotNull('aplicacion_id')->pluck('aplicacion_id')->flip();
        $propiasEscritorio = $enEscritorio->whereNotNull('aplicacion_propia_id')->pluck('aplicacion_propia_id')->flip();

        $propias = AplicacionPropia::query()
            ->where('user_id', $usuario->id)
            ->orderByRaw('lower(nombre)')
            ->orderBy('id')
            ->get()
            ->map(fn (AplicacionPropia $propia): AplicacionDelPanel => new AplicacionDelPanel(
                OrigenAplicacion::Propia, $propia->id, $propia->nombre, $propia->enlace, $propia->icono,
                (int) ($dePropias[$propia->id] ?? 0), $propia->version,
                escritorio: $propia->escritorio, enEscritorio: $propia->escritorio !== null && isset($propiasEscritorio[$propia->id]),
            ));
        $techquk = $techquk->map(fn (AplicacionDelPanel $aplicacion): AplicacionDelPanel => $aplicacion->escritorio === null || ! isset($techqukEscritorio[$aplicacion->id]) ? $aplicacion
            : new AplicacionDelPanel($aplicacion->origen, $aplicacion->id, $aplicacion->nombre, $aplicacion->enlace, $aplicacion->icono, $aplicacion->usos, $aplicacion->version, $aplicacion->imagen, $aplicacion->escritorio, true));

        // Plan 1.4z, D10: su orden, dentro de cada sección; sin orden propio, el de administración (y las propias, por
        // nombre). Las que no tienen posición guardada (se sumaron después) van al final, en ese orden.
        $orden = OrdenAplicacion::query()->where('user_id', $usuario->id)->get();
        $enSuOrden = function (Collection|\Illuminate\Support\Collection $lista, array $posiciones): array {
            $lista = $lista->values()->all();
            $indices = array_keys($lista);
            usort($indices, fn (int $a, int $b): int => [$posiciones[$lista[$a]->id] ?? PHP_INT_MAX, $a] <=> [$posiciones[$lista[$b]->id] ?? PHP_INT_MAX, $b]);

            return array_map(fn (int $indice): AplicacionDelPanel => $lista[$indice], $indices);
        };

        return [
            'techquk' => $enSuOrden($techquk, $orden->whereNotNull('aplicacion_id')->pluck('posicion', 'aplicacion_id')->all()),
            'propias' => $enSuOrden($propias, $orden->whereNotNull('aplicacion_propia_id')->pluck('posicion', 'aplicacion_propia_id')->all()),
        ];
    }

    private function llenar(AplicacionPropia $propia, DatosAplicacionPropia $datos): void
    {
        $nombre = trim($datos->nombre);

        if ($nombre === '') {
            throw AplicacionRechazada::nombreVacio();
        }

        if ($datos->icono === IconoAplicacion::Propio) {
            throw AplicacionRechazada::iconoPropio();
        }

        $enlace = RedesSocialesService::normalizar($datos->enlace) ?? throw AplicacionRechazada::enlaceInvalido();

        if (! str_starts_with($enlace, 'https://')) {
            throw AplicacionRechazada::enlaceSinHttps();
        }

        $propia->fill(['nombre' => $nombre, 'enlace' => $enlace, 'icono' => $datos->icono, 'escritorio' => $datos->escritorio]);
    }

    /** Guarda en su propio punto de guardado y traduce el nombre repetido a un mensaje claro. */
    private function guardar(AplicacionPropia $propia): void
    {
        try {
            DB::transaction(fn (): bool => $propia->save());
        } catch (QueryException $e) {
            // 23505: unique_violation.
            throw ($e->errorInfo[0] ?? null) === '23505' && str_contains($e->getMessage(), 'aplicaciones_propias_nombre_unique')
                ? AplicacionRechazada::nombreRepetido() : $e;
        }
    }

    /** Solo las suyas: la de otra persona no se delata (dice lo mismo que una que no existe). */
    private function bloquear(User $usuario, int $id, int $versionLeida): AplicacionPropia
    {
        $propia = AplicacionPropia::query()->lockForUpdate()->whereKey($id)->where('user_id', $usuario->id)->first()
            ?? throw AplicacionRechazada::noDisponible();

        if ($propia->version !== $versionLeida) {
            throw VersionDesactualizada::de('la aplicación');
        }

        return $propia;
    }

    private static function clave(OrigenAplicacion $origen, int $id): string
    {
        return "{$origen->value}:{$id}";
    }
}
