<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\RedesSocialesService;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
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
 * Pestaña Redes de la ficha de una party (plan 1.4o, D2): las de la persona o empresa primero y luego las de cada
 * contacto. Todo por las acciones registradas `nucleo.parties.*-red-social`; cada formulario toma la versión al
 * abrirse. Las de una persona del equipo se ven sin botones (las cambia ella desde «Mis datos») y sin las que marcó
 * «solo la veo yo» (D3), salvo para ella misma.
 */
final class RedesDeParty extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer las redes de otra party. */
    #[Locked]
    public int $partyId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('parties.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $delEquipo = app(RedesSocialesService::class)->esDelEquipo($this->partyId);
        $contacto = ['contacto_id' => fn (): Field => SelectoresParty::contactoDe('contacto_id', $this->partyId)
            ->placeholder('De la persona o empresa')];

        return $table
            ->query(fn (): Builder => PartyRedSocial::query()->with(['redSocial', 'contacto'])->where('party_id', $this->partyId)
                ->when($this->usuario()->party_id !== $this->partyId, fn (Builder $query): Builder => $query->where('es_privada', false))
                ->orderByRaw('contacto_id IS NOT NULL')->orderBy('contacto_id')->orderBy('red_social_id')->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('red_social_id')->label('Red')
                    ->formatStateUsing(fn (PartyRedSocial $record): string => $record->nombreDeLaRed())
                    ->icon(fn (PartyRedSocial $record): string => $record->redSocial->icono->componente())
                    ->description(fn (PartyRedSocial $record): ?string => $record->es_privada ? 'Solo la ve la persona' : null),
                TextColumn::make('enlace')->label('Enlace')->wrap()->color('primary')
                    ->url(fn (PartyRedSocial $record): string => $record->enlace, shouldOpenInNewTab: true),
                TextColumn::make('contacto_id')->label('De')
                    ->state(fn (PartyRedSocial $record): string => $record->contacto?->nombreCompleto() ?? 'La persona o empresa')
                    ->hidden($delEquipo),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.parties.agregar-red-social', fn (): array => ['party_id' => $this->partyId], $this->avisarGuardado(...), controles: $contacto)
                    ->label('Agregar red')->icon('heroicon-o-plus')
                    ->modalDescription('De la persona o empresa, o de uno de sus contactos. El enlace debe ser de la red elegida.')
                    ->hidden($delEquipo),
            ])
            ->recordActions([
                ActionGroup::make([
                    AccionFilament::desde('nucleo.parties.cambiar-red-social', fn (PartyRedSocial $record): array => ['red_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Cambiar')->icon('heroicon-o-pencil-square')
                        ->fillForm(fn (PartyRedSocial $record): array => [...$record->only(['red_social_id', 'detalle', 'enlace']), 'version' => $record->version]),
                    AccionFilament::desde('nucleo.parties.quitar-red-social', fn (PartyRedSocial $record): array => ['red_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Quitar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                        ->fillForm(fn (PartyRedSocial $record): array => ['version' => $record->version]),
                ])->icon('heroicon-o-ellipsis-vertical')->hidden($delEquipo),
            ])
            ->emptyStateHeading('Sin redes')
            ->emptyStateDescription($delEquipo
                ? 'Es una persona del equipo: sus redes las agrega ella misma desde «Mis datos».'
                : 'Agrega su LinkedIn, su Facebook o su sitio web, y los de sus contactos.')
            ->description($delEquipo ? 'Es una persona del equipo: sus redes las cambia ella misma desde «Mis datos».' : null);
    }

    public function render(): View
    {
        return view('nucleo::filament.redes-de-party');
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-party-cambiada');
    }
}
