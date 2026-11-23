<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\AccionesContrato;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\Contrato;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** MVP rápido (t2-contratos): las adendas, que cambian el monto y el plazo vigentes (y con ellos la penalidad). */
final class AdendasRelationManager extends RelationManager
{
    protected static string $relationship = 'adendas';

    protected static ?string $title = 'Adendas';

    public function table(Table $table): Table
    {
        return $table->paginated(false)
            ->columns([
                TextColumn::make('numero')->label('Adenda'),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('motivo')->label('Motivo')->wrap(),
                TextColumn::make('monto_adicional')->label('Monto adicional (con IGV)')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => ContratoResource::soles($state)),
                TextColumn::make('dias_adicionales')->label('Días más')->alignEnd(),
            ])
            ->headerActions([
                AccionesContrato::adenda(fn (): Contrato => $this->getOwnerRecord(), nombre: 'registrar')->icon('heroicon-o-plus'),
            ]);
    }
}
