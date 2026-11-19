<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t1-licitaciones): la pestaña «Consultas» de la ficha de una licitación. */
final class ConsultasRelationManager extends RelationManager
{
    protected static string $relationship = 'consultas';

    protected static ?string $title = 'Consultas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function isReadOnly(): bool
    {
        return ! LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha')->label('Fecha')->required()->default(now()),
            Textarea::make('pregunta')->label('Consulta u observación')->required(),
            Textarea::make('respuesta')->label('Absolución'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('fecha')
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('pregunta')->label('Consulta u observación')->wrap(),
                TextColumn::make('respuesta')->label('Absolución')->wrap()->placeholder('Sin absolver'),
            ])
            ->headerActions([CreateAction::make()->label('Agregar')->label('Registrar consulta')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
