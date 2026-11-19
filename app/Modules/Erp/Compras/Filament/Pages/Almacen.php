<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Services\ComprasService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t3-compras): el stock del Almacén Central con lo reservado y lo disponible, y su valor. */
final class Almacen extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'almacen';

    protected static ?string $title = 'Almacén';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    public static function canAccess(): bool
    {
        return Compras::puede(ComprasService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $valor = DB::table('stock')->selectRaw('COALESCE(SUM(cantidad * costo_promedio), 0) AS v')->value('v');

        return 'Almacén Central · valor del stock '.Compras::soles($valor).' a costo promedio. Lo reservado está apartado para requerimientos aprobados y sale con su despacho.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $n = fn ($v): string => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');

        return $table
            ->records(fn (): array => DB::table('stock as s')->join('productos as p', 'p.id', '=', 's.producto_id')->join('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
                ->orderBy('p.codigo')->get(['s.id', 'p.codigo', 'p.descripcion', 'u.abreviatura as und', 's.cantidad', 's.reservado', 's.costo_promedio'])
                ->map(fn (object $r): array => ['__key' => (string) $r->id, ...(array) $r, 'disponible' => bcsub((string) $r->cantidad, (string) $r->reservado, 3)])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono'),
                TextColumn::make('descripcion')->label('Ítem')->wrap(),
                TextColumn::make('und')->label('Und'),
                TextColumn::make('cantidad')->label('Stock')->alignEnd()->formatStateUsing($n),
                TextColumn::make('reservado')->label('Reservado')->alignEnd()->formatStateUsing($n)->color(fn ($state): ?string => (float) $state > 0 ? 'warning' : null),
                TextColumn::make('disponible')->label('Disponible')->alignEnd()->weight('bold')->formatStateUsing($n),
                TextColumn::make('costo_promedio')->label('Costo prom.')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
            ]);
    }
}
