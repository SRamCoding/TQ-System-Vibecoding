<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\MisDatos;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\User;
use Filament\Actions\ActionGroup;
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
use Livewire\Component;

/**
 * «Mis redes» en «Mis datos» (plan 1.4o, D2 y D3): las redes de la propia persona, y red por red si la ve todo el
 * equipo o solo ella. Por las acciones `nucleo.*-mi-red-social`; cada formulario toma la versión al abrirse. Siempre
 * las de quien tiene la sesión: no recibe de quién son.
 */
final class MisRedesSociales extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PartyRedSocial::query()->with('redSocial')
                ->where('party_id', $this->usuario()->party_id ?? 0)->whereNull('contacto_id')
                ->orderBy('red_social_id')->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('red_social_id')->label('Red')
                    ->formatStateUsing(fn (PartyRedSocial $record): string => $record->nombreDeLaRed())
                    ->icon(fn (PartyRedSocial $record): string => $record->redSocial->icono->componente()),
                TextColumn::make('enlace')->label('Enlace')->wrap()->color('primary')
                    ->url(fn (PartyRedSocial $record): string => $record->enlace, shouldOpenInNewTab: true),
                TextColumn::make('es_privada')->label('La ve')
                    ->formatStateUsing(fn (PartyRedSocial $record): string => $record->es_privada ? 'Solo yo' : 'Todo el equipo')
                    ->icon(fn (PartyRedSocial $record): string => $record->es_privada ? 'heroicon-o-lock-closed' : 'heroicon-o-user-group')
                    ->color(fn (PartyRedSocial $record): string => $record->es_privada ? 'gray' : 'success'),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.agregar-mi-red-social', alTerminar: $this->avisarGuardado(...))
                    ->label('Agregar red')->icon('heroicon-o-plus')
                    ->modalDescription('Pega el enlace a tu perfil. Si marcas «Solo la veo yo», nadie más del equipo la ve.')
                    ->hidden($this->usuario()->party_id === null),
            ])
            ->recordActions([
                ActionGroup::make([
                    AccionFilament::desde('nucleo.cambiar-mi-red-social', fn (PartyRedSocial $record): array => ['red_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Cambiar')->icon('heroicon-o-pencil-square')
                        ->fillForm(fn (PartyRedSocial $record): array => [...$record->only(['red_social_id', 'detalle', 'enlace', 'es_privada']), 'version' => $record->version]),
                    AccionFilament::desde('nucleo.quitar-mi-red-social', fn (PartyRedSocial $record): array => ['red_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Quitar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                        ->fillForm(fn (PartyRedSocial $record): array => ['version' => $record->version]),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->emptyStateHeading('Sin redes')
            ->emptyStateDescription('Agrega tu LinkedIn, tu Instagram o tu sitio web.');
    }

    public function render(): View
    {
        return view('nucleo::filament.mis-redes-sociales');
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
    }
}
