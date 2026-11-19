<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias\RelationManagers;

use App\Modules\Crm\Campanias\Models\Lead;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t1-campanias): los leads de la campaña (se atienden en «Campañas y leads»). */
final class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads';

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nombre_contacto')->label('Nombre'),
                TextColumn::make('organizacion')->label('Organización')->placeholder('—'),
                TextColumn::make('created_at')->label('Llegó')->date('d/m/Y'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => Lead::ESTADOS[$state])->color(fn (string $state): string => Lead::COLORES[$state]),
            ]);
    }
}
