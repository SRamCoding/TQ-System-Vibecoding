<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Models\Comprobante;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MV-021: «Cobranzas», junto a «Facturas emitidas»: lo que los clientes deben, lo vencido primero, y lo cobrado en los
 * últimos 60 días. El cobro es el de siempre (FinanzasService::cobrar).
 */
final class Cobranzas extends Comprobantes
{
    protected const string CLASE = 'venta';

    protected static ?string $slug = 'cobranzas';

    protected static ?string $title = 'Cobranzas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    public function getSubheading(): string
    {
        $hoy = Calendario::hoy();
        $por = DB::table('comprobantes')->where('clase', 'venta')->where('estado', 'por_cobrar');
        $total = (clone $por)->sum('saldo');
        $vencido = (clone $por)->where('vencimiento', '<', $hoy)->sum('saldo');
        $n = (clone $por)->where('vencimiento', '<', $hoy)->count();

        return 'Por cobrar S/ '.self::s($total).' · vencido S/ '.self::s($vencido)." ({$n} ".($n === 1 ? 'factura' : 'facturas').').';
    }

    public function table(Table $table): Table
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->where('estado', 'por_cobrar')
                ->orWhere(fn (Builder $c) => $c->where('estado', 'cobrado')->where('updated_at', '>=', Calendario::ahora()->subDays(60)))))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw("estado = 'cobrado'")->orderBy('vencimiento'))
            ->pushColumns([
                TextColumn::make('plazo')->label('Plazo')->badge()
                    ->state(function (Comprobante $record) use ($hoy): string {
                        if ($record->estado === 'cobrado') {
                            return 'Cobrada';
                        }

                        $dias = (int) $hoy->diffInDays($record->vencimiento, false);

                        return $dias < 0 ? 'Vencida hace '.abs($dias).' d' : ($dias === 0 ? 'Vence hoy' : "Vence en {$dias} d");
                    })
                    ->color(fn (Comprobante $record): string => $record->estado === 'cobrado' ? 'success' : ($record->vencimiento->lt($hoy) ? 'danger' : ($hoy->diffInDays($record->vencimiento) <= 7 ? 'warning' : 'gray'))),
            ]);
    }
}
