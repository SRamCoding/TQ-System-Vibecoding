<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios\Pages;

use App\Modules\Nucleo\Filament\Resources\Usuarios\UsuarioResource;
use App\Modules\Nucleo\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * La ficha de una cuenta (plan 1.4, D10): Datos, Roles y Roles por unidad. Arriba, cambiar puesto, desactivar y
 * activar, con la versión tomada al abrir.
 *
 * @property-read User $record
 */
final class VerUsuario extends ViewRecord
{
    protected static string $resource = UsuarioResource::class;

    /** Una pestaña cambió sus roles: la ficha se vuelve a dibujar. */
    #[On('ficha-usuario-cambiada')]
    public function alCambiarLaFicha(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): string
    {
        return $this->record->email.($this->record->es_activo ? '' : ' · Desactivada');
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return UsuarioResource::accionesDeCuenta(function (): void {
            $this->record->refresh();
        });
    }
}
