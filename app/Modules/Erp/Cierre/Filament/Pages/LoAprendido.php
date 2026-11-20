<?php

declare(strict_types=1);

namespace App\Modules\Erp\Cierre\Filament\Pages;

use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t9-cierre): los proyectos cerrados con su margen real, dónde se desviaron y lo aprendido, para Preventa. */
final class LoAprendido extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'lo-aprendido';

    protected static ?string $title = 'Lo aprendido de los proyectos cerrados';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can('erp.proyectos.ver');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => DB::table('proyecto_cierres as c')->join('proyectos as p', 'p.id', '=', 'c.proyecto_id')->orderByDesc('c.fecha')->get(['c.*', 'p.codigo', 'p.nombre'])
                ->map(function (object $c): array {
                    $desvios = collect(json_decode($c->partidas, true))->filter(fn (array $p): bool => (float) $p['diferencia'] > 0)->sortByDesc(fn (array $p): float => (float) $p['diferencia'])->take(3)
                        ->map(fn (array $p): string => "{$p['codigo']} {$p['nombre']} +".number_format((float) $p['diferencia'], 2))->implode(' · ');

                    return ['__key' => (string) $c->id, ...(array) $c, 'desvios' => $desvios ?: 'sin partidas por encima de lo presupuestado'];
                })->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Proyecto')->weight('bold')->description(fn (array $record): string => $record['nombre'])->url(fn (array $record): string => "/proyectos/{$record['proyecto_id']}?pestana=cierre::tab")->wrap(),
                TextColumn::make('fecha')->label('Cerrado')->date('d/m/Y'),
                TextColumn::make('margen_real')->label('Margen real')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->description(fn (array $record): string => $record['margen_pct'] !== null ? $record['margen_pct'].' %' : '')->color(fn ($state): ?string => (float) $state < 0 ? 'danger' : null),
                TextColumn::make('desvios')->label('Dónde se pasó')->wrap(),
                TextColumn::make('lecciones')->label('Lo aprendido')->wrap()->placeholder('—'),
            ]);
    }
}
