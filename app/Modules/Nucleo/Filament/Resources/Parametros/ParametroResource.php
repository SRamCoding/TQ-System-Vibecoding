<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parametros;

use App\Modules\Nucleo\Acciones\CambiarParametro;
use App\Modules\Nucleo\Acciones\CambiarParametroSensible;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Parametros\Pages\ListarParametros;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\ParametrosService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros del negocio (M9): solo lectura más la acción registrada «Cambiar parámetro».
 */
final class ParametroResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Parametro::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $modelLabel = 'parámetro';

    protected static ?string $pluralModelLabel = 'parámetros';

    protected static ?string $slug = 'parametros';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('catalogos.ver');
    }

    /*
     * Solo lectura: los cambios pasan por acciones registradas (servicio, validación, versión). Sin
     * esto, Filament permitiría crear, editar y borrar a quien pueda ver la pantalla si alguien agrega
     * esos botones.
     */
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
            ->defaultSort('grupo')
            ->columns([
                TextColumn::make('grupo')->label('Grupo')->sortable()->searchable(),
                // Para qué sirve, bajo su clave: en una columna propia quedaba angosta y la tabla no cabía.
                TextColumn::make('clave')->label('Clave y para qué sirve')->searchable()->wrap()
                    ->description(fn (Parametro $record): string => $record->descripcion),
                // Una lista larga (las extensiones) se corta; completa, al pasar el mouse y al cambiarla.
                TextColumn::make('valor')->label('Valor')->fontFamily('mono')->limit(24)
                    ->tooltip(fn (Parametro $record): ?string => mb_strlen($record->valor) > 24 ? $record->valor : null),
                TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (Parametro $record): string => $record->tipo->value),
                IconColumn::make('es_editable')->label('Editable')->boolean(),
            ])
            ->recordActions([
                self::accionCambiar(app(CambiarParametro::class), sensible: false)
                    ->label('Cambiar')
                    ->icon('heroicon-o-pencil-square'),
                // Los que deciden a dónde va el dinero, con su permiso (plan 1.3b; como los catálogos, plan 1.2b E6).
                self::accionCambiar(app(CambiarParametroSensible::class), sensible: true)
                    ->label('Cambiar (dinero)')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning'),
            ]);
    }

    private static function accionCambiar(Accion $accion, bool $sensible): Action
    {
        return AccionFilament::desde(
            $accion,
            fn (Parametro $record): array => ['parametro' => self::nombre($record)],
            function (): void {
                Notification::make()->success()->title('Parámetro actualizado.')->send();
            },
        )
            ->modalDescription(fn (Parametro $record): string => "Se espera {$record->tipo->formatoEsperado()}.")
            // La versión se captura al abrir: si otro guarda mientras tanto, el servicio lo rechaza.
            ->fillForm(fn (Parametro $record): array => ['valor' => $record->valor, 'descripcion' => $record->descripcion, 'version' => $record->version])
            ->hidden(fn (Parametro $record): bool => ! $record->es_editable
                || app(ParametrosService::class)->esSensible(self::nombre($record)) !== $sensible);
    }

    private static function nombre(Parametro $record): string
    {
        return "{$record->grupo}.{$record->clave}";
    }

    public static function getPages(): array
    {
        return ['index' => ListarParametros::route('/')];
    }
}
