<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parametros;

use App\Modules\Nucleo\Filament\Clusters\ReglasNegocio;
use App\Modules\Nucleo\Filament\Resources\Parametros\Pages\ListarParametrosNegocio;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * MV-016: los parámetros que deciden cómo trabaja Techquk (márgenes, probabilidades, plazos del embudo, IGV, semáforos
 * del presupuesto y los sensibles de dinero), en «Reglas del negocio». La misma tabla y las mismas acciones que
 * ParametroResource; solo cambia qué filas muestra.
 */
final class ParametroNegocioResource extends ParametroResource
{
    protected static ?string $cluster = ReglasNegocio::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationLabel = 'Parámetros del negocio';

    protected static ?string $slug = 'parametros';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getModel()::query()->whereIn('id', self::idsDelNegocio());
    }

    public static function getPages(): array
    {
        return ['index' => ListarParametrosNegocio::route('/')];
    }
}
