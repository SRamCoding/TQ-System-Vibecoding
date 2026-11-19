<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Ordenes;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Filament\Resources\Ordenes\Pages\ListarOrdenes;
use App\Modules\Erp\Compras\Filament\Resources\Ordenes\Pages\VerOrden;
use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Compras\Models\OrdenLinea;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Services\ImputacionService;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** MVP rápido (ficha t3-compras): las órdenes de compra y de servicio, con su seguimiento. */
final class OrdenResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Orden::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $modelLabel = 'orden';

    protected static ?string $pluralModelLabel = 'órdenes';

    protected static ?string $slug = 'ordenes';

    public static function canViewAny(): bool
    {
        return Compras::puede(ComprasService::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function total(Orden $o): string
    {
        return bcadd((string) ($o->lineas->sum('subtotal') ?: '0'), '0', 2);
    }

    public static function color(string $estado): string
    {
        return match ($estado) {
            'aprobada' => 'info', 'recibida_parcial' => 'warning', 'recibida' => 'success', 'anulada' => 'danger', default => 'gray'
        };
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lineas', 'tipo']))
            ->columns([
                TextColumn::make('codigo')->label('Orden')->fontFamily('mono')->searchable()->description(fn (Orden $record): string => $record->tipo->nombre),
                TextColumn::make('proveedor_nombre')->label('Proveedor')->searchable()->wrap(),
                TextColumn::make('proyecto')->label('Proyecto')->state(fn (Orden $record): string => $record->proyecto_id === null ? 'Gasto directo' : (string) DB::table('proyectos')->where('id', $record->proyecto_id)->value('codigo')),
                TextColumn::make('total')->label('Monto')->alignEnd()->fontFamily('mono')->state(fn (Orden $record): string => Compras::soles(self::total($record))),
                TextColumn::make('entrega_pactada')->label('Entrega')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ComprasService::ESTADOS_ORDEN[$state])->color(fn (string $state): string => self::color($state)),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(ComprasService::ESTADOS_ORDEN),
                SelectFilter::make('clase')->label('Clase')->options(['compra' => 'Órdenes de compra', 'servicio' => 'Órdenes de servicio']),
            ])
            ->recordUrl(fn (Orden $record): string => VerOrden::getUrl(['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        $imp = fn (OrdenLinea $l) => app(ImputacionService::class)->de($l);

        return $schema->components([
            Section::make('Ítems')->schema([
                RepeatableEntry::make('lineas')->hiddenLabel()->columns(7)->schema([
                    TextEntry::make('descripcion')->label('Ítem')->columnSpan(2),
                    TextEntry::make('cantidad')->label('Cant.')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.')),
                    TextEntry::make('precio_unitario')->label('P. unit.')->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                    TextEntry::make('subtotal')->label('Subtotal')->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                    TextEntry::make('imputacion')->label('Imputación')->state(fn (OrdenLinea $record): string => Compras::imputacion($record->partida_control_id, $record->centro_costo_id)),
                    TextEntry::make('cantidad_recibida')->label('Recibido')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.'))
                        ->helperText(fn (OrdenLinea $record): ?string => ($i = $imp($record)) === null ? null : 'comprometido '.number_format((float) $i->monto_comprometido, 2).' · ejecutado '.number_format((float) $i->monto_ejecutado, 2)),
                ]),
            ]),
            Section::make('Seguimiento')->columns(4)->schema([
                TextEntry::make('entrega_pactada')->label('Entrega pactada')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('lugar_entrega')->label('Lugar')->placeholder('—'),
                TextEntry::make('forma_pago')->label('Forma de pago')->placeholder('—'),
                TextEntry::make('aprobado')->label('Aprobó')->state(fn (Orden $record): string => $record->aprobado_por === null ? '—' : DB::table('users')->where('id', $record->aprobado_por)->value('name').($record->motivo_exceso === null ? '' : ' · motivo del exceso registrado')),
                TextEntry::make('seguimiento')->label('Seguimiento')->columnSpanFull()->placeholder('—'),
                TextEntry::make('motivo_exceso')->label('Motivo del exceso')->columnSpanFull()->placeholder('—'),
                TextEntry::make('cuadro')->label('Cuadro comparativo')->columnSpanFull()
                    ->state(fn (Orden $record): string => $record->cuadro_id === null ? 'Sin cuadro (orden directa)' : DB::table('cuadros_comparativos')->where('id', $record->cuadro_id)->value(DB::raw("codigo || ' · ' || COALESCE(motivo, '')"))),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarOrdenes::route('/'), 'view' => VerOrden::route('/{record}')];
    }
}
