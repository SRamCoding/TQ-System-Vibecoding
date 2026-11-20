<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Valorizaciones\Models\Subcontrato;
use App\Modules\Erp\Valorizaciones\Models\SubcontratoValorizacion;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t4-valorizaciones): los subcontratos y las valorizaciones de cada subcontratista. */
final class Subcontratos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'subcontratos';

    protected static ?string $title = 'Subcontratos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(ValorizacionesService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        return Subcontrato::query()->orderBy('codigo')->get()->map(fn (Subcontrato $s): string => "{$s->codigo} {$s->subcontratista}: ".Compras::soles($s->monto)
            .', retención acumulada '.Compras::soles(app(ValorizacionesService::class)->retencionAcumulada($s->id)))->implode(' · ')
            .' · Lo que la entidad le hace a Techquk, Techquk se lo hace a su subcontratista. Cada valorización aprobada pasa a ejecutado en las partidas del alcance.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $m = fn (string $c, string $l, bool $menos = false) => TextColumn::make($c)->label($l)->alignEnd()->fontFamily('mono')
            ->formatStateUsing(fn ($state): string => ($menos ? '−' : '').number_format((float) $state, 2));

        return $table
            ->query(fn () => SubcontratoValorizacion::query())
            ->defaultSort('subcontrato_id')
            ->paginated(false)
            ->columns([
                TextColumn::make('subcontrato')->label('Subcontrato')->state(fn (SubcontratoValorizacion $record): string => (string) Subcontrato::query()->whereKey($record->subcontrato_id)->value('codigo'))
                    ->description(fn (SubcontratoValorizacion $record): string => (string) Subcontrato::query()->whereKey($record->subcontrato_id)->value('subcontratista')),
                TextColumn::make('numero')->label('Val.')->formatStateUsing(fn ($state, SubcontratoValorizacion $record): string => "{$state} · {$record->periodo}"),
                TextColumn::make('avance_pct')->label('Avance')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.').' %'),
                $m('monto', 'Monto'),
                $m('amortizacion', 'Amort. adelanto', true),
                $m('retencion', 'Retención', true),
                TextColumn::make('neto')->label('Neto a pagar')->alignEnd()->fontFamily('mono')->weight('bold')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ['por_aprobar' => 'Por aprobar', 'aprobada' => 'Aprobada', 'pagada' => 'Pagada'][$state])
                    ->color(fn (string $state): string => $state === 'por_aprobar' ? 'warning' : 'success'),
            ])
            ->recordActions([
                Action::make('aprobar')->label('Aprobar')->button()->color('success')->requiresConfirmation()->hidden(fn (SubcontratoValorizacion $record): bool => $record->estado !== 'por_aprobar')
                    ->action(fn (SubcontratoValorizacion $record) => app(ValorizacionesService::class)->aprobarSubcontrato($record->id)),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $puede = fn (): bool => auth()->user()?->can(ValorizacionesService::PERMISO_GESTIONAR) ?? false;

        return [
            Action::make('nuevo')->label('Nuevo subcontrato')->icon('heroicon-o-plus')->color('gray')->visible($puede)->slideOver()
                ->schema([
                    Select::make('proyecto')->label('Proyecto')->required()->live()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('e.codigo', 'en_ejecucion')->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    TextInput::make('subcontratista')->label('Subcontratista')->required(),
                    TextInput::make('objeto')->label('Objeto')->required(),
                    TextInput::make('adelanto')->label('Adelanto (%)')->numeric()->default(20),
                    TextInput::make('retencion')->label('Retención de garantía (%)')->numeric()->default(5),
                    Repeater::make('alcance')->label('Alcance por partida')->columns(2)->schema([
                        Select::make('partida')->label('Partida')->required()->options(fn (Get $get): array => Compras::partidasDe(is_numeric($get('../../proyecto')) ? (int) $get('../../proyecto') : null)),
                        TextInput::make('monto')->label('Monto')->numeric()->required(),
                    ]),
                ])
                ->action(fn (array $data) => app(ValorizacionesService::class)->crearSubcontrato((int) $data['proyecto'], $data['subcontratista'], $data['objeto'], (string) $data['adelanto'], (string) $data['retencion'],
                    collect($data['alcance'])->mapWithKeys(fn (array $a): array => [(int) $a['partida'] => (string) $a['monto']])->all())),
            Action::make('valorizar')->label('Valorización del subcontratista')->icon('heroicon-o-plus')->visible($puede)
                ->fillForm(fn (): array => ['periodo' => Calendario::ahora()->format('Y-m')])
                ->schema([
                    Select::make('subcontrato')->label('Subcontrato')->required()->options(fn (): array => Subcontrato::query()->pluck('codigo', 'id')->all()),
                    TextInput::make('periodo')->label('Periodo (AAAA-MM)')->required(),
                    TextInput::make('avance')->label('Avance del periodo (%)')->numeric()->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(ValorizacionesService::class)->valorizarSubcontrato((int) $data['subcontrato'], $data['periodo'], (string) $data['avance']);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
