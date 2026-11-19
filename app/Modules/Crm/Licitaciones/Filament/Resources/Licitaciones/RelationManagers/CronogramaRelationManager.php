<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t1-licitaciones): la pestaña «Cronograma» de la ficha de una licitación. */
final class CronogramaRelationManager extends RelationManager
{
    protected static string $relationship = 'hitos';

    protected static ?string $title = 'Cronograma';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function isReadOnly(): bool
    {
        return ! LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('etapa')->label('Etapa')->required()->maxLength(80),
            DatePicker::make('fecha')->label('Fecha')->required(),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->required()->default(fn (): int => (int) $this->getOwnerRecord()->hitos()->max('orden') + 1),
            Toggle::make('es_cumplido')->label('Cumplida'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('etapa')->label('Etapa'),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')
                    ->color(fn (Model $record): ?string => ! $record->es_cumplido && $record->fecha->isPast() ? 'danger' : null),
                IconColumn::make('es_cumplido')->label('Cumplida')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Agregar')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
