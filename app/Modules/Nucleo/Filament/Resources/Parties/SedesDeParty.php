<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartySede;
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
 * Pestaña Sedes de la ficha de una party (plan 1.3b, D3 y D4): sucursales, almacenes y puntos de entrega, las
 * activas primero. La dirección fiscal no es una sede (está en Datos) y la de una obra es del proyecto. Todo por
 * las acciones registradas `nucleo.parties.*-sede`; cada formulario toma la versión al abrirse.
 */
final class SedesDeParty extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer las sedes de otra party. */
    #[Locked]
    public int $partyId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('parties.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $contacto = ['contacto_id' => fn (): Field => SelectoresParty::contactoDe('contacto_id', $this->partyId)];

        return $table
            ->query(fn (): Builder => PartySede::query()->with(['ubigeo', 'contacto'])->where('party_id', $this->partyId)
                ->orderByDesc('es_activa')->orderBy('nombre'))
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')->label('Nombre')
                    ->description(fn (PartySede $record): ?string => $record->es_activa ? null : 'Desactivada'),
                TextColumn::make('direccion')->label('Dirección')->wrap()
                    ->description(fn (PartySede $record): ?string => $record->ubigeo?->etiqueta()),
                TextColumn::make('codigo_establecimiento')->label('Anexo SUNAT')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('contacto_id')->label('Contacto')->placeholder('—')
                    ->formatStateUsing(fn (PartySede $record): string => $record->contacto?->nombreCompleto() ?? '—'),
                TextColumn::make('latitud')->label('Coordenadas')->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (PartySede $record): string => "{$record->latitud}, {$record->longitud}"),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.parties.agregar-sede', fn (): array => ['party_id' => $this->partyId], $this->avisarGuardado(...), controles: $contacto)
                    ->label('Agregar sede')->icon('heroicon-o-plus')
                    ->modalDescription('Sucursal, almacén o punto de entrega. La dirección fiscal está en Datos; la de una obra va en su proyecto.'),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.parties.editar-sede', fn (PartySede $record): array => ['sede_id' => $record->id], $this->avisarGuardado(...), controles: $contacto)
                    ->label('Editar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (PartySede $record): array => [
                        ...$record->only(['nombre', 'direccion', 'ubigeo_id', 'latitud', 'longitud', 'contacto_id', 'codigo_establecimiento']),
                        'version' => $record->version,
                    ]),
                ActionGroup::make([
                    AccionFilament::desde('nucleo.parties.desactivar-sede', fn (PartySede $record): array => ['sede_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                        ->modalDescription('Deja de ofrecerse; lo ya registrado la conserva. Su nombre y su anexo quedan libres.')
                        ->fillForm(fn (PartySede $record): array => ['version' => $record->version])
                        ->hidden(fn (PartySede $record): bool => ! $record->es_activa),
                    AccionFilament::desde('nucleo.parties.activar-sede', fn (PartySede $record): array => ['sede_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Activar')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                        ->fillForm(fn (PartySede $record): array => ['version' => $record->version])
                        ->hidden(fn (PartySede $record): bool => $record->es_activa),
                    AccionFilament::desde('nucleo.parties.borrar-sede', fn (PartySede $record): array => ['sede_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Borrar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                        ->modalDescription('Solo se borra una sede nunca usada. Si ya se usó, desactívala.')
                        ->fillForm(fn (PartySede $record): array => ['version' => $record->version]),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->emptyStateHeading('Sin sedes')
            ->emptyStateDescription('Agrega sus sucursales, almacenes o puntos de entrega.');
    }

    public function render(): View
    {
        return view('nucleo::filament.sedes-de-party');
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-party-cambiada');
    }
}
