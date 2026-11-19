<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestaña Roles de la ficha de una party (plan 1.3, D12): sus periodos, vigentes y cerrados, con «Asignar
 * rol» arriba y «Cerrar» en cada periodo abierto. Todo por las acciones registradas `nucleo.parties.*-rol`.
 */
final class RolesDeParty extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer los roles de otra party. */
    #[Locked]
    public int $partyId;

    /** El componente se puede montar aparte de la ficha: revisa el mismo permiso que ella. */
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('parties.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PartyRol::query()->with('rol')->where('party_id', $this->partyId))
            ->defaultSort('desde', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('rol.nombre')->label('Rol'),
                TextColumn::make('desde')->label('Desde')->date('d/m/Y'),
                TextColumn::make('hasta')->label('Hasta')->date('d/m/Y')->placeholder('Sin fin'),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.parties.asignar-rol', fn (): array => ['party_id' => $this->partyId], $this->avisarGuardado(...))
                    ->label('Asignar rol')->icon('heroicon-o-plus')
                    ->fillForm(['desde' => Calendario::hoy()]),
            ])
            ->recordActions([
                AccionFilament::desde(
                    'nucleo.parties.cerrar-rol',
                    fn (PartyRol $record): array => ['party_id' => $record->party_id, 'rol_party_id' => $record->rol_party_id],
                    $this->avisarGuardado(...),
                )
                    ->label('Cerrar')->icon('heroicon-o-stop-circle')->color('gray')
                    ->modalDescription('La fecha es el último día en que tiene el rol.')
                    ->fillForm(fn (PartyRol $record): array => ['hasta' => Calendario::hoy(), 'version' => $record->version])
                    ->hidden(fn (PartyRol $record): bool => $record->hasta !== null),
            ])
            ->emptyStateHeading('Sin roles')
            ->emptyStateDescription('Asígnale un rol: cliente, proveedor, entidad…');
    }

    public function render(): View
    {
        return view('nucleo::filament.roles-de-party');
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        // La ficha vuelve a dibujar su historial (era una limitación de la 1.3; plan 1.3b, paso 6).
        $this->dispatch('ficha-party-cambiada');
    }
}
