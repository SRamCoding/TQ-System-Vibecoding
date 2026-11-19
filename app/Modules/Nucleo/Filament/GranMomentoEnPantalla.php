<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\GranMomentoService;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * El gran momento en la pantalla de la persona (plan 1.4d, D5). Se dibuja al final de la página solo si tiene uno
 * pendiente, esperando: cuando la pestaña se ve, marca «Lo vi» con la acción registrada y solo lo empieza si fue la
 * primera marca; si ya lo vio en otra pestaña (o la marca falla), lo quita sin mostrarlo.
 */
final class GranMomentoEnPantalla extends Component
{
    #[Locked]
    public int $granMomento;

    /** Marca «Lo vi» y dice si fue la primera vez. No vuelve a dibujar el componente: el diálogo sigue como está. */
    #[Renderless]
    public function marcar(): bool
    {
        $usuario = Filament::auth()->user();

        // En modo desarrollo se mira, no se marca: el modo no guarda nada en la base (plan 1.4i).
        if (! $usuario instanceof User || ModoDesarrollo::activo()) {
            return false;
        }

        try {
            $resultado = app(EjecutorAcciones::class)->ejecutar('nucleo.marcar-momento-visto', ['gran_momento' => $this->granMomento], $usuario);
        } catch (ErrorDeNegocio|ValidationException) {
            return false;
        }

        return is_array($resultado) && ($resultado['primera'] ?? false) === true;
    }

    public function render(): View
    {
        return view('nucleo::filament.gran-momento.en-pantalla', ['escena' => app(GranMomentoService::class)->escena($this->granMomento)]);
    }
}
