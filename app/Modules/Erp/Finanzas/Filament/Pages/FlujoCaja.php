<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Models\MovimientoCaja;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** MVP rápido (t5-finanzas): el flujo de caja (real y proyectado) y los movimientos de caja y banco, conciliables. */
final class FlujoCaja extends Base implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'flujo-caja';

    protected static ?string $title = 'Flujo de caja y conciliación';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-up-down';

    public function content(Schema $schema): Schema
    {
        $desde = Calendario::ahora()->subMonths(5)->format('Y-m');

        return $schema->components([
            View::make('finanzas::flujo')->viewData(['filas' => app(FinanzasService::class)->flujo($desde, 9)]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => MovimientoCaja::query())
            ->defaultSort('fecha', 'desc')
            ->heading('Movimientos de caja y banco')
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('cuenta')->label('Cuenta'),
                TextColumn::make('concepto')->label('Concepto')->wrap()->searchable(),
                TextColumn::make('monto')->label('Monto')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn ($state, MovimientoCaja $record): string => ($record->tipo === 'egreso' ? '−' : '+').self::s($state))
                    ->color(fn (MovimientoCaja $record): string => $record->tipo === 'egreso' ? 'danger' : 'success'),
                TextColumn::make('conciliado')->label('Conciliado')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'conciliado' : 'pendiente')->color(fn (bool $state): string => $state ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('cuenta')->label('Cuenta')->options(array_combine(FinanzasService::CUENTAS, FinanzasService::CUENTAS)),
                TernaryFilter::make('conciliado')->label('Conciliado'),
            ])
            ->recordActions([
                Action::make('conciliar')->label(fn (MovimientoCaja $record): string => $record->conciliado ? 'Desconciliar' : 'Conciliar')->color('gray')
                    ->action(fn (MovimientoCaja $record) => app(FinanzasService::class)->conciliar($record->id, ! $record->conciliado)),
            ]);
    }
}
