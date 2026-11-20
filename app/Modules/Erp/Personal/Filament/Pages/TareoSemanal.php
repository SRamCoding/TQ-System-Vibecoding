<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Erp\Personal\Models\Tareo;
use App\Modules\Erp\Personal\Models\TareoLinea;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use RuntimeException;

/** MVP rápido (t4-personal-tareo): el tareo semanal de un proyecto, por persona, día y partida; cerrar la semana lo vuelve costo. */
final class TareoSemanal extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'tareo';

    protected static ?string $title = 'Tareo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    #[Url]
    public ?int $proyecto = null;

    #[Url]
    public ?string $semana = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    public function mount(): void
    {
        $this->proyecto ??= (int) DB::table('tareos')->orderByDesc('semana')->value('proyecto_id') ?: null;
        $this->semana ??= (string) (DB::table('tareos')->where('proyecto_id', $this->proyecto)->orderByDesc('semana')->value('semana') ?? CarbonImmutable::parse(Calendario::hoy())->startOfWeek()->toDateString());
    }

    private function tareo(): ?Tareo
    {
        return $this->proyecto === null ? null : Tareo::query()->where('proyecto_id', $this->proyecto)->where('semana', $this->semana)->first();
    }

    public function getSubheading(): string
    {
        $t = $this->tareo();
        $lunes = CarbonImmutable::parse($this->semana);
        $pry = DB::table('proyectos')->where('id', $this->proyecto)->value('codigo') ?? 'elige un proyecto';
        $horas = $t === null ? '0' : $t->lineas->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->horas(), 1), '0');
        $costo = $t === null ? '0' : $t->lineas->reduce(fn (string $s, TareoLinea $l): string => bcadd($s, $l->costo(), 2), '0');

        return "Semana {$lunes->isoWeek()} ({$lunes->format('d/m')} al {$lunes->addDays(5)->format('d/m')}) · {$pry} · ".($t?->estado === 'cerrado' ? 'cerrada' : 'abierta')
            ." · {$horas} h · ".Compras::soles($costo);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $cols = [
            TextColumn::make('persona')->label('Persona')->weight('bold'),
            TextColumn::make('partida')->label('Partida')->wrap(),
        ];

        foreach (PersonalService::DIAS as $d => $etiqueta) {
            $cols[] = TextColumn::make($d)->label($etiqueta)->alignEnd()->formatStateUsing(fn ($state): string => (float) $state === 0.0 ? '' : rtrim(rtrim((string) $state, '0'), '.'));
        }

        $cols[] = TextColumn::make('horas')->label('Horas')->alignEnd()->weight('bold');
        $cols[] = TextColumn::make('costo')->label('Costo')->alignEnd()->fontFamily('mono');

        return $table
            ->records(fn (): array => ($this->tareo()?->lineas ?? collect())->map(fn (TareoLinea $l): array => [
                '__key' => (string) $l->id, 'persona' => Colaborador::query()->whereKey($l->colaborador_id)->value('nombres').' · '.mb_strtolower((string) DB::table('categorias_personal')->join('colaboradores', 'colaboradores.categoria_id', '=', 'categorias_personal.id')->where('colaboradores.id', $l->colaborador_id)->value('categorias_personal.nombre')),
                'partida' => (string) DB::table('partidas_control')->where('id', $l->partida_control_id)->value(DB::raw("codigo || ' ' || nombre")),
                ...collect(PersonalService::DIAS)->keys()->mapWithKeys(fn (string $d): array => [$d => (string) $l->{$d}])->all(),
                'horas' => rtrim(rtrim($l->horas(), '0'), '.'), 'costo' => number_format((float) $l->costo(), 2),
            ])->values()->all())
            ->paginated(false)
            ->columns($cols)
            ->emptyStateHeading('Sin horas esta semana')
            ->emptyStateDescription('Agrega las horas de cada persona por día y partida.');
    }

    protected function getHeaderActions(): array
    {
        $abierta = fn (): bool => $this->proyecto !== null && ($this->tareo()?->estado ?? 'abierto') === 'abierto';

        return [
            Action::make('elegir')->label('Proyecto y semana')->icon('heroicon-o-adjustments-horizontal')->color('gray')
                ->fillForm(fn (): array => ['proyecto' => $this->proyecto, 'semana' => $this->semana])
                ->schema([
                    Select::make('proyecto')->label('Proyecto')->required()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->whereIn('e.codigo', ['en_preparacion', 'en_ejecucion'])->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    DatePicker::make('semana')->label('Cualquier día de la semana')->required(),
                ])
                ->action(function (array $data): void {
                    $this->proyecto = (int) $data['proyecto'];
                    $this->semana = CarbonImmutable::parse($data['semana'])->startOfWeek()->toDateString();
                    $this->resetTable();
                }),
            Action::make('horas')->label('Agregar horas')->icon('heroicon-o-plus')->visible(fn (): bool => $abierta() && (auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false))
                ->schema([
                    Select::make('colaborador')->label('Persona')->required()->options(fn (): array => Colaborador::query()->where('es_activo', true)->orderBy('nombres')->pluck('nombres', 'id')->all()),
                    Select::make('partida')->label('Partida')->required()->options(fn (): array => Compras::partidasDe($this->proyecto)),
                    Grid::make(6)->schema(collect(PersonalService::DIAS)->map(fn (string $e, string $d) => TextInput::make($d)->label($e)->numeric()->minValue(0)->maxValue(16))->values()->all()),
                    TextInput::make('autorizacion')->label('Autorización con motivo (solo si su habilitación venció)')->maxLength(300),
                ])
                ->action(function (array $data): void {
                    try {
                        $s = app(PersonalService::class);
                        $s->tarear($s->semana((int) $this->proyecto, CarbonImmutable::parse($this->semana))->id, (int) $data['colaborador'], (int) $data['partida'], collect(PersonalService::DIAS)->keys()->mapWithKeys(fn ($d): array => [$d => (string) ($data[$d] ?? '0')])->all(), filled($data['autorizacion'] ?? null) ? $data['autorizacion'] : null);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    $this->resetTable();
                }),
            Action::make('cerrar')->label('Cerrar semana')->icon('heroicon-o-lock-closed')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => $abierta() && $this->tareo() !== null && (auth()->user()?->can(PersonalService::PERMISO_CERRAR) ?? false))
                ->action(function (): void {
                    try {
                        app(PersonalService::class)->cerrar($this->tareo()->id, auth()->user());
                        Notification::make()->success()->title('Semana cerrada: las horas son costo del proyecto.')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
