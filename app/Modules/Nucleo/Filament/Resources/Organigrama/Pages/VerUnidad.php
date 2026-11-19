<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Organigrama\Pages;

use App\Modules\Nucleo\Filament\Resources\Organigrama\UnidadOrganizativaResource;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * La ficha de una unidad (plan 1.4, D10): sus datos, su jefatura y sus puestos.
 *
 * @property-read UnidadOrganizativa $record
 */
final class VerUnidad extends ViewRecord
{
    protected static string $resource = UnidadOrganizativaResource::class;

    #[On('ficha-unidad-cambiada')]
    public function alCambiarLaFicha(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return $this->record->nombre;
    }

    public function getSubheading(): string
    {
        return "{$this->record->codigo} · {$this->record->tipo->etiqueta()}".($this->record->es_activa ? '' : ' · Desactivada');
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return UnidadOrganizativaResource::accionesDeUnidad(function (): void {
            $this->record->refresh();
        });
    }
}
