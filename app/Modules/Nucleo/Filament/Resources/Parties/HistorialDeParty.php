<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\TablaHistorial;
use App\Modules\Nucleo\Models\Party;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

/**
 * Historial de la ficha de una party (plan 1.3b, paso 6): la party, sus roles, contactos y sedes, y sus cuentas
 * solo para quien puede verlas (`PartyResource::modelosDelHistorial`). Se pone al día cuando una pestaña guarda.
 */
final class HistorialDeParty extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('parties.ver') ?? false, 403);
    }

    /** Una pestaña guardó un cambio: la tabla se vuelve a dibujar con él. */
    #[On('ficha-party-cambiada')]
    public function alCambiarLaFicha(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        return PartyResource::modelosDelHistorial(Party::query()->findOrFail($this->registroId));
    }
}
