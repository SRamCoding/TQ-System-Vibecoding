<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Models\Comprobante;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t5-finanzas): comprobantes de venta y de compra; la bandeja por facturar y las facturas de proveedor. */
final class Comprobantes extends Base implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'comprobantes';

    protected static ?string $title = 'Comprobantes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public const array ESTADOS = ['por_cobrar' => 'Por cobrar', 'cobrado' => 'Cobrado', 'por_programar' => 'Por programar', 'programado' => 'Programado', 'pagado' => 'Pagado', 'anulado' => 'Anulado'];

    public function getSubheading(): string
    {
        $porFacturar = DB::table('valorizaciones')->where('estado', 'aprobada')->count();

        return ($porFacturar > 0 ? "Por facturar: {$porFacturar} valorizaciones aprobadas · " : '').'Las facturas de proveedor entran por su XML (validado con SUNAT) y se cruzan con la orden y su recepción.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Comprobante::query())
            ->defaultSort('emision', 'desc')
            ->columns([
                TextColumn::make('serie_numero')->label('Comprobante')->fontFamily('mono')->searchable()
                    ->description(fn (Comprobante $record): string => ($record->clase === 'venta' ? 'Venta' : 'Compra').' · '.['xml' => 'XML', 'manual' => 'a mano', 'emitido' => 'emitido'][$record->origen]),
                TextColumn::make('razon_social')->label('Cliente o proveedor')->wrap()->searchable()->description(fn (Comprobante $record): ?string => $record->ruc),
                TextColumn::make('proyecto')->label('Proyecto')->state(fn (Comprobante $record): string => $record->proyecto_id === null ? '—' : (string) DB::table('proyectos')->where('id', $record->proyecto_id)->value('codigo')),
                TextColumn::make('emision')->label('Emisión')->date('d/m/Y')->description(fn (Comprobante $record): string => 'vence '.$record->vencimiento->format('d/m/Y')),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => self::s($state))
                    ->description(fn (Comprobante $record): ?string => (float) $record->detraccion > 0 ? 'detracción '.self::s($record->detraccion) : null),
                TextColumn::make('cruce')->label('Cruce / SUNAT')->badge()->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === 'diferencia' ? 'Diferencia con la recepción' : 'Orden ✓ recepción ✓ factura ✓')
                    ->color(fn (?string $state): string => $state === 'diferencia' ? 'danger' : 'success'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => self::ESTADOS[$state])
                    ->color(fn (string $state): string => match ($state) {
                        'cobrado', 'pagado' => 'success', 'por_cobrar', 'programado' => 'info', 'por_programar' => 'warning', default => 'gray'
                    }),
            ])
            ->filters([
                SelectFilter::make('clase')->label('Clase')->options(['venta' => 'Ventas', 'compra' => 'Compras']),
                SelectFilter::make('estado')->label('Estado')->options(self::ESTADOS),
            ])
            ->recordActions([
                Action::make('cobrar')->label('Cobrar')->button()->color('success')->hidden(fn (Comprobante $record): bool => $record->estado !== 'por_cobrar' || ! self::puede(FinanzasService::PERMISO_FACTURAR))
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'cuenta' => FinanzasService::CUENTAS[0]])
                    ->schema([DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'), Select::make('cuenta')->label('Cuenta')->options(array_combine(FinanzasService::CUENTAS, FinanzasService::CUENTAS))->required()])
                    ->action(fn (Comprobante $record, array $data) => $this->hacer(fn () => app(FinanzasService::class)->cobrar($record->id, CarbonImmutable::parse($data['fecha']), $data['cuenta']), 'Cobrado.')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('facturar')->label('Facturar valorización')->icon('heroicon-o-document-plus')->visible(fn (): bool => self::puede(FinanzasService::PERMISO_FACTURAR))
                ->fillForm(fn (): array => ['emision' => Calendario::hoy()])
                ->schema([
                    Select::make('valorizacion')->label('Valorización aprobada')->required()
                        ->options(fn (): array => DB::table('valorizaciones as v')->join('contratos as c', 'c.id', '=', 'v.contrato_id')->where('v.estado', 'aprobada')
                            ->selectRaw("v.id, c.codigo || ' · val. ' || v.numero || ' · ' || v.periodo || ' · neto ' || to_char(v.neto, 'FM999G999G990D00') AS t")->pluck('t', 'v.id')->all()),
                    DatePicker::make('emision')->label('Emisión')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(FinanzasService::class)->facturarValorizacion((int) $data['valorizacion'], CarbonImmutable::parse($data['emision'])), 'Factura emitida por el neto.')),
            Action::make('compra')->label('Registrar factura de proveedor')->icon('heroicon-o-arrow-up-tray')->color('gray')->visible(fn (): bool => self::puede(FinanzasService::PERMISO_PAGAR))
                ->modalDescription('En el MVP se escriben los datos del XML; la validación con SUNAT y la lectura del archivo son simuladas. Se cruza con la orden y lo recibido.')
                ->fillForm(fn (): array => ['emision' => Calendario::hoy(), 'dias' => 30])
                ->schema([
                    Select::make('orden')->label('Orden')->required()->searchable()
                        ->options(fn (): array => DB::table('ordenes')->whereIn('estado', ['recibida', 'recibida_parcial'])->whereNotExists(fn ($q) => $q->from('comprobantes')->whereColumn('comprobantes.orden_id', 'ordenes.id'))
                            ->orderByDesc('id')->selectRaw("id, codigo || ' · ' || proveedor_nombre AS t")->limit(200)->pluck('t', 'id')->all()),
                    TextInput::make('serie')->label('Serie y número')->required()->placeholder('F001-00004512'),
                    TextInput::make('ruc')->label('RUC del proveedor')->maxLength(11),
                    DatePicker::make('emision')->label('Emisión')->required()->format('Y-m-d'),
                    TextInput::make('dias')->label('Días de crédito')->numeric()->integer()->required(),
                    TextInput::make('subtotal')->label('Subtotal facturado (vacío: lo recibido)')->numeric(),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(FinanzasService::class)->registrarCompra((int) $data['orden'], $data['serie'], $data['ruc'] ?? null, CarbonImmutable::parse($data['emision']), (int) $data['dias'],
                    filled($data['subtotal'] ?? null) ? (string) $data['subtotal'] : null), 'Factura registrada: pasa al tablero de pagos.')),
        ];
    }
}
