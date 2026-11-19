<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;

/**
 * Lo que se revisa de una foto subida, para la de perfil (plan 1.4o, D6) y la de un proyecto (plan 2.61): que el
 * contenido sea una imagen y un tope alto contra lo absurdo. El recorte y el achicado los hace el navegador.
 */
final class RevisionDeFoto
{
    /** Una foto de celular sin achicar pesa unos pocos MB; esto solo frena lo absurdo. */
    public const int TOPE_MB = 20;

    /** Lo que `finfo` puede decir del contenido, con la extensión con que se guarda. */
    private const array TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @return string la extensión con que se guarda
     *
     * @throws FotoRechazada
     */
    public static function extension(ArchivoSubido $foto): string
    {
        if (! is_file($foto->ruta)) {
            throw FotoRechazada::noEsImagen();
        }

        if ((int) filesize($foto->ruta) > self::TOPE_MB * 1024 * 1024) {
            throw FotoRechazada::muyGrande(self::TOPE_MB);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($foto->ruta);

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            throw FotoRechazada::heic();
        }

        return self::TIPOS[$mime] ?? throw FotoRechazada::noEsImagen();
    }
}
