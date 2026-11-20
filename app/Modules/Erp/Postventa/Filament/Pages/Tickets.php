<?php

declare(strict_types=1);

namespace App\Modules\Erp\Postventa\Filament\Pages;

use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Erp\Postventa\Services\PostventaService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t6-postventa): la bandeja de tickets, ordenada por vencimiento del nivel de servicio, no por creación. */
final class Tickets extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'postventa';

    protected static ?string $title = 'Tickets y series';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench';

    public const array ESTADOS = ['abierto' => 'Abierto', 'en_atencion' => 'En atención', 'con_fabricante' => 'Con fabricante', 'por_cotizar' => 'Por cotizar (fuera de garantía)', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PostventaService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $vencer = DB::table('tickets_postventa')->whereNotIn('estado', ['resuelto', 'cerrado'])->where('vence_at', '<', Calendario::ahora()->addHours(4))->count();

        return "{$vencer} por vencer su SLA en 4 horas o vencidos";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $ahora = Calendario::ahora();

        return $table
            ->records(fn (?array $filters): array => DB::table('tickets_postventa as t')->join('cuentas as c', 'c.id', '=', 't.cuenta_id')->join('parties as p', 'p.id', '=', 'c.party_id')
                ->when(($v = $filters['vista']['value'] ?? 'abiertos') === 'abiertos', fn ($q) => $q->whereNotIn('t.estado', ['resuelto', 'cerrado']))
                ->when($v === 'mios', fn ($q) => $q->where('t.responsable_user_id', auth()->id())->whereNotIn('t.estado', ['resuelto', 'cerrado']))
                ->when($v === 'por_vencer', fn ($q) => $q->whereNotIn('t.estado', ['resuelto', 'cerrado'])->where('t.vence_at', '<', $ahora->addHours(4)))
                ->when($v === 'fabricante', fn ($q) => $q->where('t.estado', 'con_fabricante'))
                ->when($v === 'cerrados', fn ($q) => $q->whereIn('t.estado', ['resuelto', 'cerrado']))
                ->orderBy('t.vence_at')->get(['t.*', 'p.razon_social as cliente'])->map(fn (object $t): array => ['__key' => (string) $t->id, ...(array) $t])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Ticket')->fontFamily('mono')->description(fn (array $record): string => ucfirst($record['prioridad'])),
                TextColumn::make('cliente')->label('Cliente')->wrap(),
                TextColumn::make('equipo')->label('Equipo')->wrap()->description(fn (array $record): string => mb_substr($record['reclamo'], 0, 80)),
                TextColumn::make('garantia')->label('Garantía')->badge()->formatStateUsing(fn (string $state): string => ['vigente' => 'vigente', 'fabricante' => 'del fabricante', 'vencida' => 'vencida'][$state])
                    ->color(fn (string $state): string => ['vigente' => 'success', 'fabricante' => 'info', 'vencida' => 'danger'][$state]),
                TextColumn::make('vence_at')->label('SLA vence en')
                    ->state(function (array $record) use ($ahora): string {
                        if (in_array($record['estado'], ['resuelto', 'cerrado'], true)) {
                            return '—';
                        }

                        $min = (int) $ahora->diffInMinutes(CarbonImmutable::parse($record['vence_at']), false);

                        return $min < 0 ? 'vencido hace '.intdiv(-$min, 60).' h' : intdiv($min, 60).' h '.($min % 60).' min';
                    })
                    ->color(fn (array $record): ?string => ! in_array($record['estado'], ['resuelto', 'cerrado'], true) && CarbonImmutable::parse($record['vence_at'])->lt($ahora->addHours(4)) ? 'danger' : null),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => self::ESTADOS[$state])
                    ->color(fn (string $state): string => match ($state) {
                        'resuelto', 'cerrado' => 'success', 'con_fabricante' => 'info', 'por_cotizar' => 'danger', default => 'warning'
                    }),
            ])
            ->filters([SelectFilter::make('vista')->label('Ver')->default('abiertos')->options(['abiertos' => 'Abiertos', 'mios' => 'Míos', 'por_vencer' => 'Por vencer SLA', 'fabricante' => 'Con fabricante', 'cerrados' => 'Cerrados'])->query(fn ($q) => $q)])
            ->recordActions([
                Action::make('visita')->label('Visita')->color('gray')->hidden(fn (array $record): bool => in_array($record['estado'], ['resuelto', 'cerrado', 'por_cotizar'], true))
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'materiales' => 0])
                    ->schema([DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'), TextInput::make('tecnico')->label('Técnico')->required(), TextInput::make('horas')->label('Horas')->numeric()->required(),
                        TextInput::make('materiales')->label('Materiales (S/)')->numeric(), TextInput::make('nota')->label('Qué se hizo')->maxLength(300)])
                    ->action(fn (array $record, array $data) => app(PostventaService::class)->visita((int) $record['id'], CarbonImmutable::parse($data['fecha']), $data['tecnico'], (string) $data['horas'], (string) ($data['materiales'] ?? '0'), $data['nota'] ?? null)),
                Action::make('estado')->label('Estado')->color('gray')->hidden(fn (array $record): bool => $record['estado'] === 'cerrado')
                    ->fillForm(fn (array $record): array => ['estado' => $record['estado'], 'solucion' => $record['solucion']])
                    ->schema([Select::make('estado')->label('Estado')->options(self::ESTADOS)->required(), Textarea::make('solucion')->label('Solución')->rows(2)])
                    ->action(fn (array $record, array $data) => app(PostventaService::class)->cambiarEstado((int) $record['id'], $data['estado'], $data['solucion'] ?? null)),
                Action::make('cotizar')->label('Cotizar')->button()->color('danger')->hidden(fn (array $record): bool => $record['garantia'] !== 'vencida' || $record['oportunidad_id'] !== null)
                    ->schema([TextInput::make('monto')->label('Monto estimado (S/)')->numeric()->required()])
                    ->action(function (array $record, array $data): void {
                        $id = app(PostventaService::class)->cotizar((int) $record['id'], (string) $data['monto'], auth()->user());
                        $this->redirect(VerOportunidad::getUrl(['record' => $id]));
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rastreo')->label('Rastrear serie')->icon('heroicon-o-magnifying-glass')->color('gray')->url(fn (): string => Rastreo::getUrl()),
            Action::make('nuevo')->label('Nuevo ticket')->icon('heroicon-o-plus')->visible(fn (): bool => auth()->user()?->can(PostventaService::PERMISO_ATENDER) ?? false)->slideOver()
                ->schema([
                    TextInput::make('serie')->label('Serie del equipo (recomendado)')->helperText('Con la serie se sabe a quién se compró, dónde está y si sigue en garantía.'),
                    Select::make('cuenta')->label('Cliente')->required()->searchable()->options(fn (): array => DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->orderBy('razon_social')->pluck('razon_social', 'cuentas.id')->all()),
                    TextInput::make('equipo')->label('Equipo (si no hay serie)'),
                    Textarea::make('reclamo')->label('Reclamo')->required()->rows(3),
                    Select::make('prioridad')->label('Prioridad')->options(['alta' => 'Alta · 8 h', 'media' => 'Media · 24 h', 'baja' => 'Baja · 72 h'])->required()->default('media'),
                ])
                ->action(function (array $data): void {
                    try {
                        app(PostventaService::class)->abrir((int) $data['cuenta'], filled($data['serie'] ?? null) ? $data['serie'] : null, $data['equipo'] ?? 'Equipo sin serie', $data['reclamo'], $data['prioridad'], auth()->id());
                        Notification::make()->success()->title('Ticket abierto.')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
