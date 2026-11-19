<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http\Controllers;

use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AdjuntosService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La única puerta a un archivo adjunto (plan 1.8, D1): con sesión, si la persona puede ver el registro y, si es
 * sensible, tiene su permiso. Siempre como descarga, nunca para abrirse dentro del sistema, con su nombre original.
 */
final class DescargarAdjunto
{
    public function __invoke(int $adjunto, AdjuntosService $adjuntos): StreamedResponse
    {
        $usuario = Filament::auth()->user();
        abort_unless($usuario instanceof User, 403);

        try {
            $archivo = $adjuntos->paraDescargar($adjunto, $usuario);
        } catch (AdjuntoRechazado) {
            abort(403);
        }

        return Storage::disk($archivo->disco)->download($archivo->ruta, $archivo->nombre_original, [
            'Content-Type' => $archivo->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
