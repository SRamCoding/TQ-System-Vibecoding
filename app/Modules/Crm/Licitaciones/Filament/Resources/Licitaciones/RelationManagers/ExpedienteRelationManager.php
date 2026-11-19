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

/** MVP rápido (t1-licitaciones): la pestaña «Expediente» de la ficha de una licitación. */
final class ExpedienteRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Expediente';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clipboard-document-check';

    public function isReadOnly(): bool
    {
        return ! LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Documento')->required()->maxLength(200),
            Toggle::make('es_listo')->label('Listo'),
            Toggle::make('es_confirmado')->label('Confirmado')->default(true),
            TextInput::make('nota')->label('Nota')->maxLength(300),
            DatePicker::make('vence')->label('Vence'),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->required()->default(fn (): int => (int) $this->getOwnerRecord()->documentos()->max('orden') + 1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('orden')
            ->description(fn (): string => ($c = $this->getOwnerRecord()->documentos()->where('es_confirmado', false)->count()) > 0
                ? "Sugerido por IA desde las bases: {$c} documentos por confirmar («Aceptar lista sugerida», arriba). No se presenta con faltantes."
                : 'Lista de verificación: no se presenta con faltantes.')
            ->columns([
                TextColumn::make('nombre')->label('Documento')->wrap()
                    ->description(fn (Model $record): ?string => $record->es_confirmado ? $record->nota : 'Sugerido por IA · por confirmar'),
                TextColumn::make('vence')->label('Vence')->date('d/m/Y')->placeholder('—'),
                IconColumn::make('es_listo')->label('Listo')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Agregar')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
