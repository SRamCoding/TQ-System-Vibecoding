<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Guarda en la base el tema que la persona elige con el selector de Filament (plan 1.4g, D4), para que la siga a
 * cualquier equipo. Escucha el evento `theme-changed` de Filament y guarda con la acción general
 * `nucleo.cambiar-preferencia`, solo si cambió.
 */
final class GuardarTema extends Component
{
    public function guardar(mixed $tema): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || ! is_string($tema)) {
            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-preferencia', ['clave' => 'apariencia.tema', 'valor' => $tema], $usuario);
        } catch (ErrorDeNegocio|ValidationException) {
            // Un valor que Filament no debería mandar: el tema sigue en el navegador, sin aviso.
        }
    }

    public function render(): View
    {
        $usuario = Filament::auth()->user();

        return view('nucleo::filament.guardar-tema', ['tema' => $usuario instanceof User ? Apariencia::tema($usuario) : null]);
    }
}
