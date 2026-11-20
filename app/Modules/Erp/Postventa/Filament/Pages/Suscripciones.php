<?php

declare(strict_types=1);

namespace App\Modules\Erp\Postventa\Filament\Pages;

use App\Modules\Erp\Postventa\Services\PostventaService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t6-postventa): las suscripciones que Techquk vende, con su margen por periodo y su disponibilidad. */
final class Suscripciones extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'suscripciones';

    protected static ?string $title = 'Suscripciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PostventaService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $n = DB::table('suscripciones')->where('renovacion', '<=', CarbonImmutable::parse(Calendario::hoy())->addDays(60)->toDateString())->count();

        return "{$n} por renovar en 60 días · El margen se mide por periodo, porque el costo también corre cada mes.";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => DB::table('suscripciones as s')->join('cuentas as c', 'c.id', '=', 's.cuenta_id')->join('parties as p', 'p.id', '=', 'c.party_id')->orderBy('s.renovacion')
                ->get(['s.*', 'p.razon_social as cliente'])->map(fn (object $s): array => ['__key' => (string) $s->id, ...(array) $s,
                    'margen' => bccomp((string) $s->cobro_mensual, '0', 2) > 0 ? bcmul(bcdiv(bcsub((string) $s->cobro_mensual, (string) $s->costo_mensual, 2), (string) $s->cobro_mensual, 6), '100', 0) : '0'])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('servicio')->label('Suscripción')->weight('bold'),
                TextColumn::make('cliente')->label('Cliente'),
                TextColumn::make('cobro_mensual')->label('Cobro mensual')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                TextColumn::make('costo_mensual')->label('Costo mensual')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->description(fn (array $record): string => "margen {$record['margen']} %"),
                TextColumn::make('disponibilidad_real')->label('Disponibilidad')->formatStateUsing(fn ($state, array $record): string => "{$state} % / meta {$record['disponibilidad_meta']} %")
                    ->color(fn (array $record): ?string => (float) $record['disponibilidad_real'] < (float) $record['disponibilidad_meta'] ? 'danger' : 'success'),
                TextColumn::make('renovacion')->label('Renueva')->date('d/m/Y'),
            ]);
    }
}
