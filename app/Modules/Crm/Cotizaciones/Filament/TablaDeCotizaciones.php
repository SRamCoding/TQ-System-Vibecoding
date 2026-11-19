<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La tabla de cotizaciones de las pestañas de la oportunidad y de la cuenta (D19): una fila por versión, por número y de
 * la versión más reciente a la más antigua (`interfaz.md`); cada una abre su ficha. El margen solo con su permiso (D15).
 */
trait TablaDeCotizaciones
{
    /** @param  \Closure(Builder<Cotizacion>): Builder<Cotizacion>  $recortar */
    protected function tablaDeCotizaciones(Table $table, \Closure $recortar, bool $conOportunidad): Table
    {
        return $table
            ->query(fn (): Builder => $recortar(Cotizacion::query()->with('estado'))->orderByDesc('numero')->orderByDesc('numero_version'))
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('numero')->label('Cotización')->fontFamily('mono')
                    ->formatStateUsing(fn (Cotizacion $record): string => "{$record->numero} v{$record->numero_version}")
                    ->description(fn (Cotizacion $record): ?string => $conOportunidad ? CotizacionResource::oportunidad($record->oportunidad_id) : null),
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Cotizacion $record): string => $record->estado->nombre.($record->es_aceptada ? ' · la aceptada' : ''))
                    ->color(fn (Cotizacion $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (Cotizacion $record): string => CotizacionResource::monto($record->moneda_id, (string) $record->total)),
                TextColumn::make('margen_porcentaje')->label('Margen')->alignEnd()->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")
                    ->visible(fn (): bool => CotizacionResource::ve(GrupoSensible::Margen)),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->placeholder('Sin emitir'),
            ])
            ->recordUrl(fn (Cotizacion $record): string => VerCotizacion::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin cotizaciones');
    }
}
