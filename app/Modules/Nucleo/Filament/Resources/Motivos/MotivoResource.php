<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Motivos;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Motivos\Pages\ListarMotivos;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Motivos (plan 1.5, D9 y D10): las listas de motivos de cada transición, con agregar, renombrar, desactivar y
 * activar por las acciones registradas. Las listas las declara el código. «Otro» no tiene desactivar.
 */
final class MotivoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Motivo::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $modelLabel = 'motivo';

    protected static ?string $pluralModelLabel = 'motivos';

    protected static ?string $slug = 'motivos';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('catalogos.ver');
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tipoMotivo'))
            // «Otro» al final de su lista.
            ->defaultSort(fn (Builder $query) => $query->orderBy('tipo_motivo_id')->orderBy('es_otro')->orderBy('nombre'))
            ->columns([
                TextColumn::make('tipoMotivo.nombre')->label('Lista'),
                TextColumn::make('nombre')->label('Motivo')->searchable(),
                IconColumn::make('es_otro')->label('Otro')->boolean(),
                IconColumn::make('es_activo')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo_motivo_id')->label('Lista')->options(fn (): array => self::listas()),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.motivos.cambiar', fn (Motivo $record): array => ['motivo_id' => $record->id], self::avisar(...))
                    ->label('Renombrar')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Motivo $record): array => ['nombre' => $record->nombre, 'version' => $record->version]),
                self::alternar('nucleo.motivos.desactivar')
                    ->label('Desactivar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->hidden(fn (Motivo $record): bool => ! $record->es_activo || $record->es_otro),
                self::alternar('nucleo.motivos.activar')
                    ->label('Activar')
                    ->icon('heroicon-o-eye')
                    ->hidden(fn (Motivo $record): bool => $record->es_activo),
            ]);
    }

    public static function accionAgregar(): Action
    {
        return AccionFilament::desde(
            'nucleo.motivos.agregar',
            alTerminar: self::avisar(...),
            controles: ['tipo_motivo_id' => fn (): Field => Select::make('tipo_motivo_id')->options(self::listas())->searchable()],
        )
            ->label('Agregar motivo')
            ->icon('heroicon-o-plus');
    }

    private static function alternar(string $clave): Action
    {
        return AccionFilament::desde($clave, fn (Motivo $record): array => ['motivo_id' => $record->id, 'version' => $record->version], self::avisar(...))
            ->requiresConfirmation();
    }

    private static function avisar(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
    }

    /**
     * @return array<int, string>
     */
    private static function listas(): array
    {
        return TipoMotivo::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public static function getPages(): array
    {
        return ['index' => ListarMotivos::route('/')];
    }
}
