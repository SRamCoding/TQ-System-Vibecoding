<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Organigrama;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestaña Puestos de la ficha de una unidad (plan 1.4, D10): cada puesto con quién lo ocupa y si es la jefatura.
 * Todo por las acciones `nucleo.organigrama.*-puesto`. Un puesto no cambia de unidad: se crea otro.
 */
final class PuestosDeUnidad extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $unidadId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('organigrama.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $fijos = fn (Puesto $record): array => ['puesto_id' => $record->id];
        $jefatura = $this->jefePuestoId();

        return $table
            ->query(fn (): Builder => Puesto::query()->with(['ocupantes' => fn ($q) => $q->where('es_activo', true)->orderBy('name')])
                ->where('unidad_organizativa_id', $this->unidadId))
            ->defaultSort('nombre')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')->label('Puesto')
                    ->description(fn (Puesto $record): ?string => $record->id === $jefatura ? 'Jefatura de la unidad' : null),
                TextColumn::make('ocupantes_activos')->label('Ocupado por')->placeholder('Vacante')
                    ->state(fn (Puesto $record): array => $record->ocupantes->map(fn (User $u): string => $u->name)->all()),
                IconColumn::make('es_activo')->label('Activo')->boolean(),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.organigrama.crear-puesto', fn (): array => ['unidad_id' => $this->unidadId], $this->avisarGuardado(...))
                    ->label('Crear puesto')->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.organigrama.editar-puesto', $fijos, $this->avisarGuardado(...))
                    ->label('Renombrar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Puesto $record): array => ['nombre' => $record->nombre, 'version' => $record->version]),
                AccionFilament::desde('nucleo.organigrama.desactivar-puesto', $fijos, $this->avisarGuardado(...))
                    ->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                    ->modalDescription('Solo si nadie activo lo ocupa y no es la jefatura.')
                    ->fillForm(fn (Puesto $record): array => ['version' => $record->version])
                    ->hidden(fn (Puesto $record): bool => ! $record->es_activo),
                AccionFilament::desde('nucleo.organigrama.activar-puesto', $fijos, $this->avisarGuardado(...))
                    ->label('Activar')->icon('heroicon-o-check-circle')->color('success')
                    ->fillForm(fn (Puesto $record): array => ['version' => $record->version])
                    ->hidden(fn (Puesto $record): bool => $record->es_activo),
            ])
            ->emptyStateHeading('Sin puestos');
    }

    public function render(): View
    {
        return view('nucleo::filament.puestos-de-unidad');
    }

    private function jefePuestoId(): ?int
    {
        $id = UnidadOrganizativa::query()->whereKey($this->unidadId)->value('jefe_puesto_id');

        return $id === null ? null : (int) $id;
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-unidad-cambiada');
    }
}
