<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament\Resources\Proyectos;

use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\Filament\TablaHistorial;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

/**
 * Historial del proyecto (plan 2.10, D3): la bitácora del núcleo, que se escribe sola. Lo del presupuesto y el árbol se
 * ve en sus propias pestañas.
 */
final class HistorialDeProyecto extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(ProyectosServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('proyecto-cambiado')]
    public function alCambiarElProyecto(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        return [Proyecto::query()->findOrFail($this->registroId)];
    }
}
