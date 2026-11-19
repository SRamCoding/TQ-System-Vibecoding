<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * El borde arrastrable de la barra lateral (plan 1.3d, D6). Al soltarlo, guarda el ancho (preferencia
 * `navegacion.ancho_barra`) con la acción general `nucleo.cambiar-preferencia` (plan 1.4g, D3), a nombre de quien la
 * arrastró.
 */
final class BordeBarraLateral extends Component
{
    public function guardar(mixed $ancho): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-preferencia', ['clave' => 'navegacion.ancho_barra', 'valor' => is_scalar($ancho) ? (string) $ancho : ''], $usuario);
        } catch (ErrorDeNegocio|ValidationException) {
            Notification::make()->danger()->title('No se pudo guardar el ancho de la barra lateral.')->send();
        }
    }

    public function render(): View
    {
        $usuario = Filament::auth()->user();

        return view('nucleo::filament.borde-barra-lateral', [
            'ancho' => $usuario instanceof User ? app(NavegacionService::class)->anchoBarra($usuario) : 0,
        ]);
    }
}
