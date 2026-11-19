<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Filament\TablaHistorial;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

/**
 * Historial de la oportunidad (plan 2.21, D11.5): la bitácora del núcleo, que se escribe sola. Junta la oportunidad y
 * sus competidores (también los ya quitados). Los cambios de etapa con su motivo están en «Historial de etapas».
 */
final class HistorialDeOportunidad extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('oportunidad-cambiada')]
    public function alCambiarLaOportunidad(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        $oportunidad = Oportunidad::query()->findOrFail($this->registroId);

        return [
            $oportunidad,
            ...$oportunidad->competidores()->get()->all(),
            ...app(HistorialCambiosService::class)->borradosDe(OportunidadCompetidor::class, 'oportunidad_id', $oportunidad->id),
        ];
    }
}
