<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Incompatibilidades;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Pages\RolesPermisos;
use App\Modules\Nucleo\Filament\Resources\Incompatibilidades\Pages\ListarIncompatibilidades;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Permisos incompatibles (plan 1.4c, D6): los pares que una misma persona no debería juntar. Agregar uno no se bloquea:
 * quien ya los junta queda como conflicto pendiente. Desactivar cierra sus conflictos. Reactivar es registrar el par de
 * nuevo. Qué pares van lo decide Gerencia (pendiente «Grilla de roles real»): aquí no viene ninguno de fábrica.
 */
final class IncompatibilidadResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = IncompatibilidadPermisos::class;

    protected static ?string $cluster = Seguridad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link-slash';

    protected static ?string $navigationLabel = 'Permisos incompatibles';

    protected static ?string $modelLabel = 'par de permisos incompatibles';

    protected static ?string $pluralModelLabel = 'permisos incompatibles';

    protected static ?string $slug = 'incompatibles';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return RolesPermisos::canAccess();
    }

    // Solo lectura: los cambios pasan por las acciones registradas.
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['permisoA', 'permisoB'])
                ->withCount(['cruces as conflictos_abiertos' => fn (Builder $cruces): Builder => $cruces->whereNull('hasta')])
                ->withExists(['cruces as conflicto_propio' => fn (Builder $cruces): Builder => $cruces->whereNull('hasta')->where('user_id', Filament::auth()->id())]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('permisoA.name')->label('Permiso')->formatStateUsing(fn (string $state): string => PermisosLegibles::etiqueta($state)),
                TextColumn::make('permisoB.name')->label('Incompatible con')->formatStateUsing(fn (string $state): string => PermisosLegibles::etiqueta($state)),
                TextColumn::make('motivo')->label('Motivo')->wrap(),
                TextColumn::make('conflictos_abiertos')->label('Conflictos abiertos'),
            ])
            ->recordActions([
                AccionFilament::desde(
                    'nucleo.seguridad.desactivar-incompatibilidad',
                    fn (IncompatibilidadPermisos $record): array => ['incompatibilidad_id' => $record->id],
                    self::avisarGuardado(...),
                )
                    ->label('Desactivar')->icon('heroicon-o-x-mark')->color('danger')
                    ->modalDescription('Deja de revisarse y sus conflictos abiertos se cierran. La historia queda.')
                    ->fillForm(fn (IncompatibilidadPermisos $record): array => ['version' => $record->version])
                    ->after(fn (ListarIncompatibilidades $livewire) => $livewire->releer())
                    // Nadie apaga el par de un conflicto suyo: lo mismo que rechazaría el servicio.
                    ->hidden(fn (IncompatibilidadPermisos $record): bool => ! $record->es_activa || (bool) $record->getAttribute('conflicto_propio')),
                AccionFilament::desde(
                    'nucleo.seguridad.registrar-incompatibilidad',
                    fn (IncompatibilidadPermisos $record): array => ['permiso_uno_id' => $record->permiso_a_id, 'permiso_otro_id' => $record->permiso_b_id],
                    self::avisarGuardado(...),
                )
                    ->name('reactivar')->label('Reactivar')->icon('heroicon-o-arrow-path')
                    ->modalDescription('Quien hoy junte estos dos permisos queda como conflicto pendiente.')
                    ->fillForm(fn (IncompatibilidadPermisos $record): array => ['motivo' => $record->motivo])
                    ->after(fn (ListarIncompatibilidades $livewire) => $livewire->releer())
                    ->hidden(fn (IncompatibilidadPermisos $record): bool => $record->es_activa),
            ])
            ->emptyStateHeading('Sin permisos incompatibles')
            ->emptyStateDescription('Agrega los pares que una misma persona no debería juntar, como pedir y aprobar.');
    }

    public static function accionAgregar(): Action
    {
        return AccionFilament::desde(
            'nucleo.seguridad.registrar-incompatibilidad',
            alTerminar: self::avisarGuardado(...),
            controles: ['permiso_uno_id' => fn () => SelectoresSeguridad::permiso('permiso_uno_id'), 'permiso_otro_id' => fn () => SelectoresSeguridad::permiso('permiso_otro_id')],
        )
            ->label('Agregar par')->icon('heroicon-o-plus')
            ->modalDescription('No se bloquea a nadie: quien ya junta los dos permisos queda como conflicto pendiente, para registrarle su control.')
            ->after(fn (ListarIncompatibilidades $livewire) => $livewire->releer());
    }

    public static function getPages(): array
    {
        return ['index' => ListarIncompatibilidades::route('/')];
    }

    private static function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
    }
}
