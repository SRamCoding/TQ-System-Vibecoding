<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Requerimientos;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Filament\Resources\Requerimientos\Pages\ListarRequerimientos;
use App\Modules\Erp\Compras\Filament\Resources\Requerimientos\Pages\VerRequerimiento;
use App\Modules\Erp\Compras\Models\CuadroComparativo;
use App\Modules\Erp\Compras\Models\Requerimiento;
use App\Modules\Erp\Compras\Models\RequerimientoLinea;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** MVP rápido (ficha t3-compras): los requerimientos, con su vista previa de partidas y su cuadro comparativo. */
final class RequerimientoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Requerimiento::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'requerimiento';

    protected static ?string $pluralModelLabel = 'requerimientos';

    protected static ?string $slug = 'requerimientos';

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

    public static function color(string $estado): string
    {
        return match ($estado) {
            'por_aprobar' => 'warning', 'aprobado' => 'info', 'atendido' => 'success', 'rechazado' => 'danger', default => 'gray'
        };
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('codigo')->label('Requerimiento')->fontFamily('mono')->searchable()
                    ->description(fn (Requerimiento $record): string => (string) DB::table('proyectos')->where('id', $record->proyecto_id)->value('codigo')),
                TextColumn::make('descripcion')->label('Para qué')->wrap()->searchable()->description(fn (Requerimiento $record): string => (string) $record->solicitante?->name),
                TextColumn::make('estimado')->label('Estimado')->alignEnd()->fontFamily('mono')
                    ->state(fn (Requerimiento $record): string => Compras::soles($record->lineas->reduce(fn (string $s, RequerimientoLinea $l): string => bcadd($s, bcmul((string) $l->cantidad, (string) $l->precio_estimado, 2), 2), '0'))),
                TextColumn::make('fecha_necesidad')->label('Se necesita')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ComprasService::ESTADOS_REQ[$state])->color(fn (string $state): string => self::color($state)),
            ])
            ->filters([SelectFilter::make('estado')->label('Estado')->options(ComprasService::ESTADOS_REQ)])
            ->recordUrl(fn (Requerimiento $record): string => VerRequerimiento::getUrl(['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        $estado = fn (string $e): string => ['pendiente' => 'por comprar', 'en_cuadro' => 'en cuadro comparativo', 'ordenada' => 'con orden', 'reservada' => 'reservada en almacén', 'despachada' => 'despachada'][$e] ?? $e;

        return $schema->components([
            Section::make('Líneas')->description('Cada línea declara qué tipo de compra es y a qué partida va. Lo que hay en almacén se atenderá con un despacho, no con una compra.')
                ->schema([
                    RepeatableEntry::make('lineas')->hiddenLabel()->columns(7)->schema([
                        TextEntry::make('descripcion')->label('Ítem')->columnSpan(2),
                        TextEntry::make('tipo.nombre')->label('Tipo de compra'),
                        TextEntry::make('cantidad')->label('Cant.')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.')),
                        TextEntry::make('stock')->label('Stock')->state(fn (RequerimientoLinea $record): string => $record->producto_id === null ? '—' : rtrim(rtrim(app(ComprasService::class)->stockDisponible($record->producto_id), '0'), '.').' en Almacén Central'),
                        TextEntry::make('imputacion')->label('Imputación')->state(fn (RequerimientoLinea $record): string => Compras::imputacion($record->partida_control_id)),
                        TextEntry::make('estimado')->label('Estimado')->fontFamily('mono')
                            ->state(fn (RequerimientoLinea $record): string => Compras::soles(bcmul((string) $record->cantidad, (string) $record->precio_estimado, 2)))
                            ->helperText(fn (RequerimientoLinea $record): ?string => $record->atencion === null ? null : $estado($record->estado)),
                    ]),
                ]),
            Section::make('Cómo queda cada partida')->schema([
                RepeatableEntry::make('vista')->hiddenLabel()->columns(5)
                    ->state(fn (Requerimiento $record): array => array_values(app(ComprasService::class)->vistaPrevia($record)))
                    ->schema([
                        TextEntry::make('codigo')->label('Partida'),
                        TextEntry::make('presupuestado')->label('Presupuestado')->fontFamily('mono')->formatStateUsing(fn ($state): string => Compras::soles($state)),
                        TextEntry::make('comprometido')->label('Ya comprometido')->fontFamily('mono')->formatStateUsing(fn ($state): string => Compras::soles($state)),
                        TextEntry::make('este')->label('Este requerimiento')->fontFamily('mono')->formatStateUsing(fn ($state): string => Compras::soles($state)),
                        TextEntry::make('porcentaje')->label('Quedaría al')->formatStateUsing(fn ($state): string => $state === null ? '—' : "{$state} %")
                            ->color(fn ($state): ?string => $state !== null && (int) $state > 100 ? 'danger' : ($state !== null && (int) $state >= 90 ? 'warning' : null)),
                    ]),
            ])->visible(fn (Requerimiento $record): bool => in_array($record->estado, ['borrador', 'por_aprobar', 'rechazado'], true)),
            Section::make('Cuadro comparativo')->schema([
                View::make('compras::cuadro')->viewData(fn (Requerimiento $record): array => ['cuadros' => CuadroComparativo::query()->with('cotizaciones.precios')->where('requerimiento_id', $record->id)->get()]),
            ])->visible(fn (Requerimiento $record): bool => CuadroComparativo::query()->where('requerimiento_id', $record->id)->exists()),
            Section::make('Aprobación')->columns(3)->schema([
                TextEntry::make('motivo_exceso')->label('Motivo del exceso')->placeholder('—'),
                TextEntry::make('comentario_rechazo')->label('Comentario del rechazo')->placeholder('—'),
                TextEntry::make('aprobado_at')->label('Aprobado el')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])->visible(fn (Requerimiento $record): bool => $record->motivo_exceso !== null || $record->comentario_rechazo !== null || $record->aprobado_at !== null),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarRequerimientos::route('/'), 'view' => VerRequerimiento::route('/{record}')];
    }
}
