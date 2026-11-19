<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Conflictos;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Pages\RolesPermisos;
use App\Modules\Nucleo\Filament\Resources\Conflictos\Pages\ListarConflictos;
use App\Modules\Nucleo\Filament\Resources\Usuarios\CamposDeControl;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Conflictos (plan 1.4c, D5): las personas que juntan dos permisos incompatibles, con su control, su responsable y su
 * vencimiento. Solo presentación: el control se registra o renueva con `nucleo.seguridad.registrar-control-cruce`.
 */
final class ConflictoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = CruceFunciones::class;

    protected static ?string $cluster = Seguridad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $modelLabel = 'conflicto';

    protected static ?string $pluralModelLabel = 'conflictos';

    protected static ?string $slug = 'conflictos';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return RolesPermisos::canAccess();
    }

    // Solo lectura: los cruces los abre y cierra el servicio; el control pasa por su acción registrada.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['usuario', 'incompatibilidad.permisoA', 'incompatibilidad.permisoB', 'controlActual.responsable']))
            ->defaultSort('desde', 'desc')
            ->columns([
                TextColumn::make('usuario.name')->label('Persona')->searchable(),
                TextColumn::make('par')->label('Junta estos permisos')->wrap()
                    ->state(fn (CruceFunciones $record): string => self::nombreDelPar($record->incompatibilidad)),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (CruceFunciones $record, ListarConflictos $livewire): string => $livewire->estado($record))
                    ->color(fn (string $state): string => match ($state) {
                        ListarConflictos::PENDIENTE => 'danger',
                        ListarConflictos::CONTROLADO => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('controlActual.control')->label('Control')->wrap()->limit(80)->placeholder('Sin control'),
                TextColumn::make('controlActual.responsable.name')->label('Responsable')->placeholder('—'),
                TextColumn::make('controlActual.vigente_hasta')->label('Vence')->date('d/m/Y')
                    ->placeholder(fn (CruceFunciones $record): string => $record->controlActual === null ? '—' : 'Sin vencimiento'),
                TextColumn::make('desde')->label('Desde')->date('d/m/Y')->sortable(),
                TextColumn::make('hasta')->label('Hasta')->date('d/m/Y')->placeholder('Abierto')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                AccionFilament::desde(
                    'nucleo.seguridad.registrar-control-cruce',
                    fn (CruceFunciones $record): array => ['cruce_id' => $record->id],
                    function (): void {
                        Notification::make()->success()->title('Guardado.')->send();
                    },
                    controles: CamposDeControl::controles(),
                )
                    ->label(fn (CruceFunciones $record): string => $record->controlActual === null ? 'Registrar control' : 'Renovar control')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->fillForm(fn (CruceFunciones $record): array => [
                        'control' => $record->controlActual?->control,
                        // Un responsable que ya no está activo no se puede volver a elegir: el servicio lo rechazaría.
                        'responsable_user_id' => $record->controlActual?->responsable?->es_activo === true ? $record->controlActual->responsable_user_id : null,
                    ])
                    ->after(fn (ListarConflictos $livewire) => $livewire->releer())
                    ->modalDescription('El responsable es otra persona, no quien tiene el conflicto. Renovar deja el control anterior en la historia.')
                    // Lo mismo que rechazaría el servicio.
                    ->hidden(fn (CruceFunciones $record): bool => $record->hasta !== null || $record->user_id === Filament::auth()->id()),
            ])
            ->emptyStateHeading('Sin conflictos')
            ->emptyStateDescription('Nadie junta permisos incompatibles. Los pares se definen en «Permisos incompatibles».');
    }

    public static function getPages(): array
    {
        return ['index' => ListarConflictos::route('/')];
    }

    public static function nombreDelPar(IncompatibilidadPermisos $par): string
    {
        return PermisosLegibles::etiqueta($par->permisoA->name).' × '.PermisosLegibles::etiqueta($par->permisoB->name);
    }
}
