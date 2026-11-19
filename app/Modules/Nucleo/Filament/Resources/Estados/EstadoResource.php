<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Estados;

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Estados\Pages\ListarEstados;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Estados de documentos (plan 1.5, D1 y D10): los estados que declaran los módulos, por tipo de documento. Solo se
 * editan nombre y color, por la acción registrada; lo demás lo declara el código. El color va siempre con el nombre
 * (`interfaz.md`): el nombre se pinta con su tono y debajo dice cuál es.
 */
final class EstadoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Estado::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $modelLabel = 'estado';

    protected static ?string $pluralModelLabel = 'estados de documentos';

    protected static ?string $navigationLabel = 'Estados de documentos';

    protected static ?string $slug = 'estados';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('catalogos.ver');
    }

    // Solo lectura: los cambios pasan por la acción registrada.
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('es_activo', true))
            ->defaultSort(fn (Builder $query) => $query->orderBy('documento_type')->orderBy('orden'))
            ->columns([
                TextColumn::make('documento_type')->label('Documento')
                    ->formatStateUsing(fn (string $state): string => self::documento($state)),
                TextColumn::make('nombre')->label('Estado')
                    ->badge()
                    ->color(fn (Estado $record): string => $record->colorEnPantalla())
                    ->description(fn (Estado $record): string => "Color: {$record->color->etiqueta()}")
                    ->searchable(),
                TextColumn::make('codigo')->label('Código')->fontFamily('mono'),
                IconColumn::make('es_editable')->label('Editable')->boolean(),
                IconColumn::make('es_inicial')->label('Inicial')->boolean(),
                IconColumn::make('es_final')->label('Final')->boolean(),
                IconColumn::make('es_cancelacion')->label('Anula')->boolean(),
            ])
            ->filters([
                SelectFilter::make('documento_type')->label('Documento')
                    ->options(fn (): array => collect(app(RegistroDocumentos::class)->definiciones())
                        ->mapWithKeys(fn ($definicion): array => [$definicion->tipo() => ucfirst($definicion->nombre())])
                        ->all()),
            ])
            ->recordActions([
                AccionFilament::desde(
                    'nucleo.estados.cambiar-presentacion',
                    fn (Estado $record): array => ['estado_id' => $record->id],
                    function (): void {
                        Notification::make()->success()->title('Estado actualizado.')->send();
                    },
                )
                    ->label('Cambiar nombre y color')
                    ->icon('heroicon-o-pencil-square')
                    // La versión se captura al abrir: si otro guarda mientras tanto, el servicio lo rechaza.
                    ->fillForm(fn (Estado $record): array => ['nombre' => $record->nombre, 'color' => $record->color->value, 'version' => $record->version]),
            ]);
    }

    private static function documento(string $tipo): string
    {
        $definicion = app(RegistroDocumentos::class)->definicionDe($tipo);

        return $definicion === null ? $tipo : ucfirst($definicion->nombre());
    }

    public static function getPages(): array
    {
        return ['index' => ListarEstados::route('/')];
    }
}
