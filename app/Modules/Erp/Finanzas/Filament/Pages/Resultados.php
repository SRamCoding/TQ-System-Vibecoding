<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use BackedEnum;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t5-finanzas): la pregunta del sistema: ¿cuánto se ganó realmente en cada proyecto? */
final class Resultados extends Base implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'resultados';

    protected static ?string $title = '¿Cuánto se ganó?';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $m = fn (string $c, string $l) => TextColumn::make($c)->label($l)->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => $state === null ? '—' : self::s($state));

        return $table
            ->records(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('p.es_interno', false)->orderBy('p.codigo')->get(['p.id', 'p.codigo', 'p.nombre', 'e.nombre as estado'])
                ->map(fn (object $p): array => ['__key' => (string) $p->id, 'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'estado' => $p->estado, ...app(FinanzasService::class)->resultado($p->id)])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Proyecto')->fontFamily('mono')->description(fn (array $record): string => mb_substr($record['nombre'], 0, 55).' · '.mb_strtolower($record['estado'])),
                $m('vendido', 'Vendido'),
                $m('facturado', 'Facturado'),
                $m('por_cobrar', 'Por cobrar'),
                $m('costo', 'Costo real'),
                $m('por_pagar', 'Por pagar'),
                TextColumn::make('margen_real')->label('Margen real')->alignEnd()->fontFamily('mono')->weight('bold')
                    ->formatStateUsing(fn ($state): string => self::s($state))->description(fn (array $record): ?string => $record['margen_pct'] === null ? null : $record['margen_pct'].' %')
                    ->color(fn ($state): ?string => (float) $state < 0 ? 'danger' : 'success'),
                $m('caja', 'Caja neta'),
            ])
            ->recordUrl(fn (array $record): string => VerProyecto::getUrl(['record' => $record['id']]));
    }
}
