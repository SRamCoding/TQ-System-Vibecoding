<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FichaComercialService;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Observación 9.5 (10): arriba de la ficha, el resumen corto de dónde está el negocio y el siguiente paso sugerido
 * («sugerido · lo confirmas tú»), con «Programar» (lo vuelve actividad) y «Volver a sugerir» (la IA, si está).
 */
final class ResumenSugeridoDeOportunidad extends Component
{
    #[Locked]
    public int $oportunidadId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    public function programar(): void
    {
        $u = Filament::auth()->user();
        abort_unless($u instanceof User, 403);

        try {
            app(FichaComercialService::class)->programarPaso($this->oportunidadId, $u);
            Notification::make()->success()->title('Paso programado: está en tu agenda para mañana a las 10:00.')->send();
            $this->dispatch('oportunidad-cambiada');
        } catch (Throwable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function volverASugerir(): void
    {
        try {
            app(FichaComercialService::class)->sugerir($this->oportunidadId);
            Notification::make()->success()->title('Resumen y siguiente paso sugeridos de nuevo.')->send();
        } catch (Throwable $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $s = app(FichaComercialService::class);

        return view('oportunidades::ficha.resumen-sugerido', ['r' => $s->resumen($this->oportunidadId), 'ia' => $s->iaDisponible()]);
    }
}
