<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\HitoContrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t2-contratos): los hitos del plazo. */
final class HitosRelationManager extends RelationManager
{
    protected static string $relationship = 'hitos';

    protected static ?string $title = 'Hitos';

    public function isReadOnly(): bool
    {
        return ! ContratoResource::puede(ContratosService::PERMISO_GESTIONAR);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Hito')->required()->maxLength(150),
            DatePicker::make('fecha_prevista')->label('Prevista')->required(),
            DatePicker::make('fecha_real')->label('Cumplida el'),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->required()->default(fn (): int => (int) $this->getOwnerRecord()->hitos()->max('orden') + 1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->paginated(false)->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label('Hito'),
                TextColumn::make('fecha_prevista')->label('Prevista')->date('d/m/Y')
                    ->color(fn (HitoContrato $record): ?string => $record->fecha_real === null && $record->fecha_prevista->toDateString() < Calendario::hoy() ? 'danger' : null),
                TextColumn::make('fecha_real')->label('Cumplida')->date('d/m/Y')->placeholder('pendiente'),
            ])
            ->headerActions([CreateAction::make()->label('Agregar hito')])
            ->recordActions([EditAction::make()]);
    }
}
