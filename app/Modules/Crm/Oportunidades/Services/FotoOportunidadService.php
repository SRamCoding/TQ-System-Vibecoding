<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
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
 * La foto de referencia de una oportunidad (plan 2.21d, D2 y D13), como la de un proyecto (2.61): una sola, recortada y
 * achicada en el navegador; aquí se revisa que sea una imagen. La cambian o quitan **su ejecutivo y Gerencia Comercial**
 * (quien puede editar todas las oportunidades, `crm.oportunidades.editar`). Disco privado de adjuntos, carpeta
 * `fotos-oportunidad`; la sirve `MostrarImagen` con la clave `oportunidad`, a quien puede ver oportunidades.
 *
 * No usa la versión de la oportunidad: subir la foto no invalida un formulario de la ficha abierto.
 */
final class FotoOportunidadService
{
    public const string CLAVE_IMAGEN = 'oportunidad';

    /**
     * @throws FotoRechazada
     * @throws OportunidadRechazada
     */
    public function cambiar(int $oportunidadId, ArchivoSubido $foto, User $usuario): void
    {
        $this->exigirQuienPuede($this->buscar($oportunidadId), $usuario);
        $extension = RevisionDeFoto::extension($foto);
        $ruta = "fotos-oportunidad/{$oportunidadId}/".Str::uuid()->toString().".{$extension}";
        $disco = Storage::disk(FotoPerfilService::DISCO);
        $flujo = fopen($foto->ruta, 'rb') ?: throw FotoRechazada::noEsImagen();

        try {
            $disco->put($ruta, $flujo);
        } finally {
            fclose($flujo);
        }

        try {
            $anterior = $this->guardar($oportunidadId, $ruta, $usuario);
        } catch (\Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }

        if ($anterior !== null) {
            $disco->delete($anterior);
        }
    }

    /** @throws OportunidadRechazada */
    public function quitar(int $oportunidadId, User $usuario): void
    {
        $this->exigirQuienPuede($this->buscar($oportunidadId), $usuario);
        $anterior = $this->guardar($oportunidadId, null, $usuario);

        if ($anterior !== null) {
            Storage::disk(FotoPerfilService::DISCO)->delete($anterior);
        }
    }

    /** Si la persona puede cambiar la foto de esa oportunidad (D13). */
    public static function puede(Oportunidad $oportunidad, User $usuario): bool
    {
        return $oportunidad->ejecutivo_user_id === $usuario->id || $usuario->can(SobreLoPropio::EDITAR);
    }

    /** La dirección de la foto, o null si no tiene. */
    public static function url(Oportunidad $oportunidad): ?string
    {
        return $oportunidad->foto_ruta === null ? null : RegistroImagenes::url(self::CLAVE_IMAGEN, $oportunidad->id, $oportunidad->foto_ruta);
    }

    /** Para `RegistroImagenes`: la ruta, si la persona puede ver oportunidades. */
    public static function rutaParaVer(int $oportunidadId, User $usuario): ?string
    {
        if (! $usuario->can(OportunidadesServiceProvider::PERMISO_VER)) {
            return null;
        }

        return Oportunidad::query()->whereKey($oportunidadId)->value('foto_ruta');
    }

    /** @return ?string la ruta anterior */
    private function guardar(int $oportunidadId, ?string $ruta, User $usuario): ?string
    {
        return DB::transaction(function () use ($oportunidadId, $ruta, $usuario): ?string {
            $oportunidad = Oportunidad::query()->lockForUpdate()->findOrFail($oportunidadId);
            // El ejecutivo pudo cambiar mientras subía.
            $this->exigirQuienPuede($oportunidad, $usuario);
            $anterior = $oportunidad->foto_ruta;
            $oportunidad->forceFill(['foto_ruta' => $ruta])->save();

            return $anterior;
        });
    }

    private function buscar(int $oportunidadId): Oportunidad
    {
        return Oportunidad::query()->find($oportunidadId) ?? throw OportunidadRechazada::conMotivo('La oportunidad no existe.');
    }

    private function exigirQuienPuede(Oportunidad $oportunidad, User $usuario): void
    {
        if (! self::puede($oportunidad, $usuario)) {
            throw OportunidadRechazada::conMotivo("Solo el ejecutivo de {$oportunidad->numero} o Gerencia Comercial cambian su foto.");
        }
    }
}
