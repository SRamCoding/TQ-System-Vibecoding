<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\TiposCambio;

use App\Modules\Nucleo\Acciones\RegistrarTipoCambio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\Pages\ListarTiposCambio;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio diario (M5): lista y la acción registrada para registrar o corregir el del día.
 */
final class TipoCambioResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = TipoCambio::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $modelLabel = 'tipo de cambio';

    protected static ?string $pluralModelLabel = 'tipos de cambio';

    protected static ?string $slug = 'tipos-cambio';

    protected static ?int $navigationSort = 2;

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
            ->modifyQueryUsing(fn ($query) => $query->with('moneda'))
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('moneda.codigo')->label('Moneda'),
                TextColumn::make('compra')->label('Compra')->fontFamily('mono')->alignEnd(),
                TextColumn::make('venta')->label('Venta')->fontFamily('mono')->alignEnd(),
                TextColumn::make('origen')->label('Origen')->badge()->formatStateUsing(fn (TipoCambio $record): string => $record->origen->value),
            ])
            ->emptyStateHeading('Aún no hay tipos de cambio')
            ->emptyStateDescription('Se traen solos de SUNAT varias veces al día. Para traerlo ya, usa «Traer de SUNAT ahora».')
            ->recordActions([
                // Corregir es esa fila: moneda y fecha fijas, solo se cambian compra y venta.
                self::accionRegistrar(fn (TipoCambio $record): array => [
                    'moneda' => (string) $record->moneda?->codigo,
                    'fecha' => $record->fecha->toDateString(),
                ])
                    ->label('Corregir')
                    ->icon('heroicon-o-pencil-square')
                    ->modalDescription(fn (TipoCambio $record): string => "{$record->moneda?->codigo} del {$record->fecha->format('d/m/Y')}")
                    ->fillForm(fn (TipoCambio $record): array => ['compra' => $record->compra, 'venta' => $record->venta]),
            ]);
    }

    /**
     * La misma acción registrada sirve para registrar (arriba) y corregir (en cada fila, con moneda y
     * fecha fijas).
     */
    public static function accionRegistrar(?Closure $datosFijos = null): Action
    {
        return AccionFilament::desde(
            app(RegistrarTipoCambio::class),
            $datosFijos,
            alTerminar: function (): void {
                Notification::make()->success()->title('Tipo de cambio guardado.')->send();
            },
        );
    }

    public static function getPages(): array
    {
        return ['index' => ListarTiposCambio::route('/')];
    }
}
