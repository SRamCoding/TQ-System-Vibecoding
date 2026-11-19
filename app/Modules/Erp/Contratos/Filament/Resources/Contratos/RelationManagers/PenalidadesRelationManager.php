<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t2-contratos): las penalidades aplicadas (se aplican con el botón de arriba). */
final class PenalidadesRelationManager extends RelationManager
{
    protected static string $relationship = 'penalidades';

    protected static ?string $title = 'Penalidades';

    public function table(Table $table): Table
    {
        return $table->paginated(false)
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('dias_atraso')->label('Días de atraso')->alignEnd(),
                TextColumn::make('monto')->label('Monto')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => ContratoResource::soles($state))->color('danger'),
                TextColumn::make('motivo')->label('Motivo')->wrap(),
            ]);
    }
}
