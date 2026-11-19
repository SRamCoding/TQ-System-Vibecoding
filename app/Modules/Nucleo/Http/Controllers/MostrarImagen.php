<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http\Controllers;

use App\Modules\Nucleo\Imagenes\RegistroImagenes;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\FotoPerfilService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Una imagen de un módulo (`RegistroImagenes`), para quien tiene sesión y la puede ver; si no, 404 (no dice si existe).
 * Se muestra en la página. Como `MostrarFoto`, la dirección cambia con cada imagen y el navegador la puede guardar.
 */
final class MostrarImagen
{
    public function __invoke(RegistroImagenes $imagenes, string $clave, int $id): StreamedResponse
    {
        $usuario = Filament::auth()->user();
        abort_unless($usuario instanceof User, 403);

        $ruta = $imagenes->ruta($clave, $id, $usuario);
        abort_if($ruta === null, 404);

        return Storage::disk(FotoPerfilService::DISCO)->response($ruta, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
