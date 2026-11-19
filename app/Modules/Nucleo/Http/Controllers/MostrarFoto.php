<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http\Controllers;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\FotoPerfilService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La foto de perfil de una persona (plan 1.4o, D4), para quien tiene sesión: la del menú y, más adelante, la del
 * directorio del equipo. Se muestra en la página (no se descarga). La dirección cambia con cada foto nueva, así que el
 * navegador la puede guardar.
 */
final class MostrarFoto
{
    public function __invoke(int $usuario): StreamedResponse
    {
        abort_unless(Filament::auth()->user() instanceof User, 403);

        $ruta = User::query()->whereKey($usuario)->value('foto_ruta');
        abort_if($ruta === null, 404);

        return Storage::disk(FotoPerfilService::DISCO)->response($ruta, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
