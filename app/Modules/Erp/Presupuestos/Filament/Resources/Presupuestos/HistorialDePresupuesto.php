<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos;

use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Nucleo\Filament\TablaHistorial;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

/**
 * Historial del presupuesto (plan 2.10, D4): la bitácora del núcleo, que se escribe sola. Junta el encabezado, los
 * títulos, las partidas y las líneas de sus APU, también los ya quitados (las líneas, de las partidas que siguen). Los montos los esconde el núcleo a quien no ve costos.
 */
final class HistorialDePresupuesto extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('presupuesto-cambiado')]
    public function alCambiar(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        $presupuesto = Presupuesto::query()->findOrFail($this->registroId);
        $historial = app(HistorialCambiosService::class);
        $partidas = PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->pluck('id')->all();

        return [
            $presupuesto,
            ...TituloDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->get()->all(),
            ...$historial->borradosDe(TituloDePresupuesto::class, 'presupuesto_id', $presupuesto->id),
            ...PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->get()->all(),
            ...$historial->borradosDe(PartidaDePresupuesto::class, 'presupuesto_id', $presupuesto->id),
            ...LineaApu::query()->whereIn('presupuesto_partida_id', $partidas)->get()->all(),
            ...array_merge(...array_map(fn (int $partidaId): array => $historial->borradosDe(LineaApu::class, 'presupuesto_partida_id', $partidaId), $partidas)),
        ];
    }
}
