<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\PlantillaVersion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\DefinicionPlantilla;
use App\Modules\Nucleo\Plantillas\OrigenDePlantilla;
use App\Modules\Nucleo\Plantillas\PropuestaDesdePlantilla;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Las plantillas personalizables para todos los módulos (ADR 0022; plan 2.11): guardar desde un documento, editar el
 * nombre, actualizar desde un documento (versión nueva), publicar en la empresa, desactivar, y aplicar (la propuesta
 * que arma su tipo). Ningún módulo lee las tablas: va por aquí.
 *
 * Quién puede qué (D1 y D2): ver y usar, quien tiene el permiso de usar del tipo (y el de su grupo sensible, si lo
 * declara, D5); una personal, solo su dueño; una de la empresa la ve todo el que puede usar el tipo, y la cambia quien
 * tiene el de publicar. El contenido nunca sale de aquí más que dentro de la propuesta del tipo.
 */
final class PlantillasService
{
    public function __construct(
        private readonly RegistroPlantillas $registro,
        private readonly SeguridadService $seguridad,
    ) {}

    /**
     * Las que la persona puede usar de un tipo: las suyas y las de la empresa, activas, con su versión vigente (D3).
     *
     * @return list<ResumenPlantilla> las propias primero, por nombre
     */
    public function disponibles(string $tipo, User $usuario): array
    {
        $definicion = $this->registro->obtener($tipo);

        if (! $this->puedeUsar($definicion, $usuario)) {
            return [];
        }

        $plantillas = Plantilla::query()->with('vigente')->where('tipo', $tipo)->where('es_activa', true)
            ->where(fn ($query) => $query->where('alcance', Plantilla::EMPRESA)->orWhere('propietario_user_id', $usuario->id))
            ->orderByRaw('propietario_user_id IS NULL')->orderBy('nombre_comparable')->get();

        return $plantillas->filter(fn (Plantilla $p): bool => $p->vigente !== null)
            ->map(fn (Plantilla $p): ResumenPlantilla => $this->resumen($p, $definicion, $usuario))->values()->all();
    }

    /** Guarda un documento como plantilla personal nueva, versión 1. */
    public function guardar(string $tipo, int $documentoId, string $nombre, ?string $descripcion, User $usuario): ResumenPlantilla
    {
        $definicion = $this->registro->obtener($tipo);
        $this->exigirUsar($definicion, $usuario);
        $contenido = $this->capturar($definicion, $documentoId, $usuario);

        return $this->sinNombreRepetido($nombre, function () use ($definicion, $documentoId, $nombre, $descripcion, $usuario, $contenido): ResumenPlantilla {
            $plantilla = Plantilla::query()->create([
                'tipo' => $definicion->clave(),
                'nombre' => self::limpio($nombre),
                'descripcion' => self::limpioONulo($descripcion),
                'alcance' => Plantilla::PERSONAL,
                'propietario_user_id' => $usuario->id,
            ]);
            $this->nuevaVersion($plantilla, $definicion, $contenido, $documentoId, 1);

            return $this->resumen($plantilla->refresh(), $definicion, $usuario);
        });
    }

    /** Cambia el nombre o la descripción; el contenido no (eso es `actualizarDesde`). */
    public function editar(int $plantillaId, string $nombre, ?string $descripcion, int $versionLeida, User $usuario): ResumenPlantilla
    {
        return $this->sinNombreRepetido($nombre, function () use ($plantillaId, $nombre, $descripcion, $versionLeida, $usuario): ResumenPlantilla {
            [$plantilla, $definicion] = $this->paraCambiar($plantillaId, $versionLeida, $usuario);
            $plantilla->fill(['nombre' => self::limpio($nombre), 'descripcion' => self::limpioONulo($descripcion)]);
            $plantilla->version++;
            $plantilla->save();

            return $this->resumen($plantilla, $definicion, $usuario);
        });
    }

    /**
     * Toma el contenido de un documento como versión siguiente (D3): lo creado con las anteriores no cambia, y desde
     * ahora se aplica esta.
     */
    public function actualizarDesde(int $plantillaId, int $documentoId, int $versionLeida, User $usuario): ResumenPlantilla
    {
        return DB::transaction(function () use ($plantillaId, $documentoId, $versionLeida, $usuario): ResumenPlantilla {
            [$plantilla, $definicion] = $this->paraCambiar($plantillaId, $versionLeida, $usuario);
            $contenido = $this->capturar($definicion, $documentoId, $usuario);
            $numero = (int) PlantillaVersion::query()->where('plantilla_id', $plantilla->id)->max('numero') + 1;
            $this->nuevaVersion($plantilla, $definicion, $contenido, $documentoId, $numero);
            $plantilla->version++;
            $plantilla->save();

            return $this->resumen($plantilla->refresh(), $definicion, $usuario);
        });
    }

    /**
     * Publica una plantilla personal en la empresa (D2): crea otra, de la empresa, con la vigente de la personal como
     * versión 1. La personal sigue siendo de su dueño.
     */
    public function publicar(int $plantillaId, ?string $nombre, User $usuario): ResumenPlantilla
    {
        $origen = Plantilla::query()->with('vigente')->find($plantillaId) ?? throw PlantillaRechazada::conMotivo('Esa plantilla no existe.');
        $definicion = $this->registro->obtener($origen->tipo);

        if ($origen->esDeLaEmpresa()) {
            throw PlantillaRechazada::conMotivo("«{$origen->nombre}» ya es de la empresa.");
        }

        if ($origen->propietario_user_id !== $usuario->id || ! $origen->es_activa) {
            throw PlantillaRechazada::conMotivo('Solo se publica una plantilla tuya y activa.');
        }

        $this->exigirUsar($definicion, $usuario);

        if (! $this->seguridad->puede($usuario, $definicion->permisoPublicar())) {
            throw PlantillaRechazada::conMotivo('Publicar plantillas de «'.$definicion->nombre().'» en la empresa necesita su permiso: pídeselo a quien administra los roles. Mientras, la tuya sigue siendo personal.');
        }

        $nombre = $nombre === null || trim($nombre) === '' ? $origen->nombre : $nombre;

        return $this->sinNombreRepetido($nombre, function () use ($origen, $definicion, $nombre, $usuario): ResumenPlantilla {
            // Con la fila bloqueada: si su dueño la actualiza o desactiva a la vez, se publica lo último, o nada.
            $origen = Plantilla::query()->lockForUpdate()->findOrFail($origen->id);

            if (! $origen->es_activa) {
                throw PlantillaRechazada::conMotivo('Solo se publica una plantilla tuya y activa.');
            }

            $vigente = $origen->vigente()->first() ?? throw PlantillaRechazada::conMotivo("«{$origen->nombre}» no tiene contenido.");
            $publicada = Plantilla::query()->create([
                'tipo' => $origen->tipo,
                'nombre' => self::limpio($nombre),
                'descripcion' => $origen->descripcion,
                'alcance' => Plantilla::EMPRESA,
                'propietario_user_id' => null,
            ]);
            PlantillaVersion::query()->create([
                'plantilla_id' => $publicada->id,
                'numero' => 1,
                'formato' => $vigente->formato,
                'contenido' => $vigente->contenido,
                'origen_documento' => $vigente->origen_documento,
                'origen_id' => $vigente->origen_id,
            ]);

            return $this->resumen($publicada->refresh(), $definicion, $usuario);
        }, empresa: true);
    }

    /** Deja de ofrecerse (no se borra); lo creado con ella sigue diciendo de dónde salió. */
    public function desactivar(int $plantillaId, int $versionLeida, User $usuario): void
    {
        DB::transaction(function () use ($plantillaId, $versionLeida, $usuario): void {
            [$plantilla] = $this->paraCambiar($plantillaId, $versionLeida, $usuario);
            $plantilla->es_activa = false;
            $plantilla->version++;
            $plantilla->save();
        });
    }

    /**
     * Aplicar (D3): la propuesta editable que arma el tipo con una copia de la versión vigente. No crea nada; el
     * módulo guarda `plantillaVersionId` en el documento cuando la persona confirma.
     *
     * @param  array<string, mixed>  $contexto  lo que el tipo necesita (por ejemplo, el proyecto)
     */
    public function aplicar(int $plantillaId, array $contexto, User $usuario): PropuestaDesdePlantilla
    {
        $plantilla = Plantilla::query()->with('vigente')->find($plantillaId) ?? throw PlantillaRechazada::conMotivo('Esa plantilla no existe.');
        $definicion = $this->registro->obtener($plantilla->tipo);
        $this->exigirVer($plantilla, $definicion, $usuario);

        if (! $plantilla->es_activa) {
            throw PlantillaRechazada::conMotivo("«{$plantilla->nombre}» está desactivada: elige otra.");
        }

        $vigente = $plantilla->vigente ?? throw PlantillaRechazada::conMotivo("«{$plantilla->nombre}» no tiene contenido.");
        $propuesta = $definicion->proponer($vigente->contenido, $vigente->formato, $contexto, $usuario);

        return new PropuestaDesdePlantilla($vigente->id, $plantilla->nombre, $vigente->numero, $propuesta);
    }

    /** De qué plantilla y versión salió un documento, para mostrarlo; null si la versión no existe. */
    public function origen(int $plantillaVersionId): ?OrigenDePlantilla
    {
        $version = PlantillaVersion::query()->with('plantilla')->find($plantillaVersionId);

        return $version === null ? null : new OrigenDePlantilla($version->plantilla_id, $version->plantilla->nombre, $version->numero);
    }

    private function puedeUsar(DefinicionPlantilla $definicion, User $usuario): bool
    {
        $grupo = $definicion->grupoSensible();

        return $this->seguridad->puede($usuario, $definicion->permisoUsar())
            && ($grupo === null || $this->seguridad->puede($usuario, $grupo->permiso()));
    }

    private function exigirUsar(DefinicionPlantilla $definicion, User $usuario): void
    {
        if (! $this->puedeUsar($definicion, $usuario)) {
            throw PlantillaRechazada::conMotivo('No tienes permiso para usar plantillas de «'.$definicion->nombre().'».');
        }
    }

    /** Una personal ajena no existe para los demás: el mensaje no dice que está ahí. */
    private function exigirVer(Plantilla $plantilla, DefinicionPlantilla $definicion, User $usuario): void
    {
        $this->exigirUsar($definicion, $usuario);

        if (! $plantilla->esDeLaEmpresa() && $plantilla->propietario_user_id !== $usuario->id) {
            throw PlantillaRechazada::conMotivo('Esa plantilla no existe.');
        }
    }

    private function puedeCambiar(Plantilla $plantilla, DefinicionPlantilla $definicion, User $usuario): bool
    {
        return $plantilla->esDeLaEmpresa()
            ? $this->seguridad->puede($usuario, $definicion->permisoPublicar())
            : $plantilla->propietario_user_id === $usuario->id;
    }

    /**
     * La plantilla bloqueada para cambiarla, con su versión leída y el permiso de quien la cambia.
     *
     * @return array{0: Plantilla, 1: DefinicionPlantilla}
     */
    private function paraCambiar(int $plantillaId, int $versionLeida, User $usuario): array
    {
        $plantilla = Plantilla::query()->lockForUpdate()->find($plantillaId) ?? throw PlantillaRechazada::conMotivo('Esa plantilla no existe.');
        $definicion = $this->registro->obtener($plantilla->tipo);
        $this->exigirVer($plantilla, $definicion, $usuario);

        if (! $this->puedeCambiar($plantilla, $definicion, $usuario)) {
            throw PlantillaRechazada::conMotivo("«{$plantilla->nombre}» es de la empresa: la cambia quien tiene el permiso de publicar plantillas de «{$definicion->nombre()}».");
        }

        if (! $plantilla->es_activa) {
            throw PlantillaRechazada::conMotivo("«{$plantilla->nombre}» está desactivada.");
        }

        if ($plantilla->version !== $versionLeida) {
            throw VersionDesactualizada::de("la plantilla «{$plantilla->nombre}»");
        }

        return [$plantilla, $definicion];
    }

    /**
     * @return array<string, mixed>
     */
    private function capturar(DefinicionPlantilla $definicion, int $documentoId, User $usuario): array
    {
        $contenido = $definicion->capturar($documentoId, $usuario);
        $problemas = $definicion->validar($contenido, $definicion->formatoActual());

        if ($problemas !== []) {
            throw PlantillaRechazada::contenidoInvalido($problemas);
        }

        return $contenido;
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function nuevaVersion(Plantilla $plantilla, DefinicionPlantilla $definicion, array $contenido, int $documentoId, int $numero): void
    {
        try {
            PlantillaVersion::query()->create([
                'plantilla_id' => $plantilla->id,
                'numero' => $numero,
                'formato' => $definicion->formatoActual(),
                'contenido' => $contenido,
                'origen_documento' => $definicion->documentoOrigen(),
                'origen_id' => $documentoId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw VersionDesactualizada::de("la plantilla «{$plantilla->nombre}»");
        }
    }

    /**
     * La base garantiza el nombre único entre las activas del mismo tipo y dueño; aquí se traduce a un mensaje.
     *
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    private function sinNombreRepetido(string $nombre, Closure $tarea, bool $empresa = false): mixed
    {
        if (trim($nombre) === '') {
            throw PlantillaRechazada::conMotivo('La plantilla necesita un nombre.');
        }

        try {
            return DB::transaction($tarea);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'plantillas_nombre_activo_unique')) {
                throw $e;
            }

            throw PlantillaRechazada::conMotivo($empresa
                ? 'La empresa ya tiene una plantilla «'.self::limpio($nombre).'» de este tipo: elige otro nombre.'
                : 'Ya tienes una plantilla «'.self::limpio($nombre).'» de este tipo: elige otro nombre o actualiza esa.');
        }
    }

    private function resumen(Plantilla $plantilla, DefinicionPlantilla $definicion, User $usuario): ResumenPlantilla
    {
        $vigente = $plantilla->vigente ?? throw PlantillaRechazada::conMotivo("«{$plantilla->nombre}» no tiene contenido.");

        return new ResumenPlantilla(
            $plantilla->id,
            $plantilla->tipo,
            $plantilla->nombre,
            $plantilla->descripcion,
            $plantilla->esDeLaEmpresa(),
            $plantilla->propietario_user_id === $usuario->id,
            $this->puedeCambiar($plantilla, $definicion, $usuario),
            $vigente->numero,
            CarbonImmutable::instance($vigente->created_at),
            $plantilla->version,
        );
    }

    private static function limpio(string $texto): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($texto));
    }

    private static function limpioONulo(?string $texto): ?string
    {
        return $texto === null || trim($texto) === '' ? null : trim($texto);
    }
}
