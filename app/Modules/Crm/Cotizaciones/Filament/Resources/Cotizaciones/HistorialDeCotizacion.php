<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Nucleo\Filament\TablaHistorial;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Filament\Facades\Filament;
use Livewire\Attributes\On;

/**
 * Historial de una versión de la cotización (D19): la bitácora del núcleo, que se escribe sola. Junta el encabezado y
 * sus líneas, también las ya quitadas. Los montos sensibles los esconde el núcleo a quien no ve costos ni márgenes.
 */
final class HistorialDeCotizacion extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(CotizacionesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('cotizacion-cambiada')]
    public function alCambiar(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        $cotizacion = Cotizacion::query()->findOrFail($this->registroId);

        return [
            $cotizacion,
            ...LineaDeCotizacion::query()->where('cotizacion_id', $cotizacion->id)->get()->all(),
            ...app(HistorialCambiosService::class)->borradosDe(LineaDeCotizacion::class, 'cotizacion_id', $cotizacion->id),
        ];
    }
}
