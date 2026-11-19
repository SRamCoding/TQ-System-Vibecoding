<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La foto de perfil de cada persona (plan 1.4o, D4 a D7): la cambia solo ella. El recorte en círculo y el achicado
 * (unos 400 × 400) los hace el navegador antes de subirla (aquí no hay GD ni Imagick), así que aquí solo se revisa que
 * el contenido sea una imagen y un tope alto contra lo absurdo. Vive en el disco privado de adjuntos; la sirve
 * MostrarFoto, con sesión.
 *
 * No usa la versión de la cuenta: solo la cambia su dueña, y subir la foto no debe invalidar el formulario de «Mis datos»
 * que tiene abierto.
 */
final class FotoPerfilService
{
    public const string DISCO = 'adjuntos';

    /** D6: el tope de `RevisionDeFoto`. */
    public const int TOPE_MB = RevisionDeFoto::TOPE_MB;

    /**
     * @throws FotoRechazada
     */
    public function cambiar(User $usuario, ArchivoSubido $foto): void
    {
        $extension = RevisionDeFoto::extension($foto);
        $ruta = "fotos-perfil/{$usuario->id}/".Str::uuid()->toString().".{$extension}";
        $disco = Storage::disk(self::DISCO);
        // Por flujo: no hace falta tener la foto entera en memoria.
        $flujo = fopen($foto->ruta, 'rb') ?: throw FotoRechazada::noEsImagen();

        try {
            $disco->put($ruta, $flujo);
        } finally {
            fclose($flujo);
        }

        try {
            $anterior = DB::transaction(function () use ($usuario, $ruta): ?string {
                $cuenta = User::query()->lockForUpdate()->findOrFail($usuario->id);
                $anterior = $cuenta->foto_ruta;
                $cuenta->forceFill(['foto_ruta' => $ruta])->save();

                return $anterior;
            });
        } catch (\Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }

        if ($anterior !== null) {
            $disco->delete($anterior);
        }
    }

    public function quitar(User $usuario): void
    {
        $anterior = DB::transaction(function () use ($usuario): ?string {
            $cuenta = User::query()->lockForUpdate()->findOrFail($usuario->id);
            $anterior = $cuenta->foto_ruta;
            $cuenta->forceFill(['foto_ruta' => null])->save();

            return $anterior;
        });

        if ($anterior !== null) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
    }
}
