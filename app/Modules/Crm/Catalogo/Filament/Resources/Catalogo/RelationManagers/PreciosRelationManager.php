<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\RelationManagers;

use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\CatalogoResource;
use App\Modules\Crm\Catalogo\Models\PrecioProducto;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t1-catalogo): la historia de precios del ítem; los precios no se sobrescriben. */
final class PreciosRelationManager extends RelationManager
{
    protected static string $relationship = 'precios';

    protected static ?string $title = 'Historia de precios';

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->description('Cada cambio crea un registro con su vigencia; el anterior queda cerrado el día antes.')
            ->columns([
                TextColumn::make('vigente_desde')->label('Desde')->date('d/m/Y'),
                TextColumn::make('vigente_hasta')->label('Hasta')->date('d/m/Y')->placeholder('vigente'),
                TextColumn::make('precio')->label('Precio')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => CatalogoResource::soles($state)),
                TextColumn::make('costo_referencial')->label('Costo ref.')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => CatalogoResource::soles($state)),
                TextColumn::make('margen_objetivo')->label('Margen obj.')->alignEnd()->formatStateUsing(fn ($state): string => $state === null ? '—' : "{$state} %"),
                TextColumn::make('origen')->label('Origen')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'carga' ? 'Carga masiva' : 'Manual'),
                TextColumn::make('es_vigente')->label('')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'vigente' : 'histórico')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordClasses(fn (PrecioProducto $record): ?string => $record->es_vigente ? null : 'opacity-70');
    }
}
