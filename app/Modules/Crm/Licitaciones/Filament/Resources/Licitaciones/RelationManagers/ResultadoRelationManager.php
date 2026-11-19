<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t1-licitaciones): la pestaña «Resultado» de la ficha de una licitación. */
final class ResultadoRelationManager extends RelationManager
{
    protected static string $relationship = 'participantes';

    protected static ?string $title = 'Resultado';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-trophy';

    public function isReadOnly(): bool
    {
        return ! LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Participante')->required()->maxLength(200),
            TextInput::make('oferta')->label('Oferta')->numeric(),
            TextInput::make('puntaje')->label('Puntaje')->numeric(),
            TextInput::make('puesto')->label('Puesto')->numeric()->integer(),
            Toggle::make('es_ganador')->label('Buena pro'),
            Toggle::make('es_techquk')->label('Somos nosotros'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('puesto')
            ->description('Participante, oferta, % del referencial y puntaje: alimenta el histórico de la entidad.')
            ->columns([
                TextColumn::make('nombre')->label('Participante')->weight(fn (Model $record): ?string => $record->es_techquk ? 'bold' : null),
                TextColumn::make('oferta')->label('Oferta')->alignEnd()->fontFamily('mono')->formatStateUsing(fn (?string $state): string => LicitacionResource::soles($state)),
                TextColumn::make('pct')->label('% del ref.')->alignEnd()
                    ->state(fn (Model $record): string => $record->oferta === null || ! $this->getOwnerRecord()->valor_referencial ? '—' : bcmul(bcdiv((string) $record->oferta, (string) $this->getOwnerRecord()->valor_referencial, 6), '100', 1).' %'),
                TextColumn::make('puntaje')->label('Puntaje')->alignEnd()->placeholder('—'),
                TextColumn::make('puesto')->label('Puesto')
                    ->formatStateUsing(fn (?int $state, Model $record): string => $state === null ? '—' : ($record->es_ganador ? 'buena pro' : "{$state}.º"))
                    ->badge()->color(fn (Model $record): string => $record->es_ganador ? 'success' : 'gray'),
            ])
            ->headerActions([CreateAction::make()->label('Agregar')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
