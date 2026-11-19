<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\Imagenes\RegistroImagenes;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\FotoPerfilService;
use App\Modules\Nucleo\Services\RevisionDeFoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La foto de referencia de un proyecto (plan 2.61, D1 y D2): una sola, recortada a 16:9 y achicada en el navegador antes
 * de subirla, como la de perfil; aquí se revisa que sea una imagen. **Solo el jefe del proyecto** la cambia o la quita.
 * Vive en el disco privado de adjuntos, carpeta `fotos-proyecto`; la sirve `MostrarImagen` con la clave `proyecto`, a
 * quien puede ver proyectos.
 *
 * No usa la versión del proyecto: subir la foto no debe invalidar un formulario de la ficha que alguien tenga abierto.
 */
final class FotoProyectoService
{
    public const string CLAVE_IMAGEN = 'proyecto';

    /**
     * @throws FotoRechazada
     * @throws ProyectoRechazado
     */
    public function cambiar(int $proyectoId, ArchivoSubido $foto, User $usuario): void
    {
        $this->exigirJefe($proyectoId, $usuario);
        $extension = RevisionDeFoto::extension($foto);
        $ruta = "fotos-proyecto/{$proyectoId}/".Str::uuid()->toString().".{$extension}";
        $disco = Storage::disk(FotoPerfilService::DISCO);
        $flujo = fopen($foto->ruta, 'rb') ?: throw FotoRechazada::noEsImagen();

        try {
            $disco->put($ruta, $flujo);
        } finally {
            fclose($flujo);
        }

        try {
            $anterior = $this->guardar($proyectoId, $ruta, $usuario);
        } catch (\Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }

        if ($anterior !== null) {
            $disco->delete($anterior);
        }
    }

    /** @throws ProyectoRechazado */
    public function quitar(int $proyectoId, User $usuario): void
    {
        $this->exigirJefe($proyectoId, $usuario);
        $anterior = $this->guardar($proyectoId, null, $usuario);

        if ($anterior !== null) {
            Storage::disk(FotoPerfilService::DISCO)->delete($anterior);
        }
    }

    /** La dirección de la foto del proyecto, o null si no tiene. */
    public static function url(Proyecto $proyecto): ?string
    {
        return $proyecto->foto_ruta === null ? null : RegistroImagenes::url(self::CLAVE_IMAGEN, $proyecto->id, $proyecto->foto_ruta);
    }

    /** Para `RegistroImagenes`: la ruta, si la persona puede ver proyectos. */
    public static function rutaParaVer(int $proyectoId, User $usuario): ?string
    {
        if (! $usuario->can(ProyectosServiceProvider::PERMISO_VER)) {
            return null;
        }

        return Proyecto::query()->whereKey($proyectoId)->value('foto_ruta');
    }

    /** @return ?string la ruta anterior */
    private function guardar(int $proyectoId, ?string $ruta, User $usuario): ?string
    {
        return DB::transaction(function () use ($proyectoId, $ruta, $usuario): ?string {
            $proyecto = Proyecto::query()->lockForUpdate()->findOrFail($proyectoId);
            // El jefe pudo cambiar mientras subía.
            $this->exigirJefeDe($proyecto, $usuario);
            $anterior = $proyecto->foto_ruta;
            $proyecto->forceFill(['foto_ruta' => $ruta])->save();

            return $anterior;
        });
    }

    private function exigirJefe(int $proyectoId, User $usuario): void
    {
        $this->exigirJefeDe(Proyecto::query()->find($proyectoId) ?? throw ProyectoRechazado::conMotivo('El proyecto no existe.'), $usuario);
    }

    private function exigirJefeDe(Proyecto $proyecto, User $usuario): void
    {
        if ($proyecto->jefe_proyecto_user_id !== $usuario->id) {
            throw ProyectoRechazado::conMotivo("Solo el jefe del proyecto {$proyecto->codigo} cambia su foto.");
        }
    }
}
