<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Filament\Pages;

use App\Modules\Crm\Actividades\Models\Actividad;
use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t1-agenda): «Mi agenda» (CRM-05). Lo pendiente primero (vencidas en rojo, hoy, lo que viene), lo
 * hecho con un filtro, las oportunidades sin próxima acción para programarlas, y «Registrar actividad» en cuatro
 * toques: tipo, relacionado con, qué pasó y próxima acción.
 */
final class Agenda extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'agenda';

    protected static ?string $title = 'Mi agenda';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(ActividadesService::PERMISO_VER);
    }

    public function getSubheading(): ?string
    {
        $sin = count($this->sinProximaAccion());
        $vencidas = Actividad::query()->where('responsable_user_id', auth()->id())->where('es_realizada', false)->where('fecha', '<', CarbonImmutable::parse(Calendario::hoy(), 'America/Lima'))->count();

        return trim(($vencidas > 0 ? "{$vencidas} vencidas · " : '').($sin > 0 ? "{$sin} oportunidades sin próxima acción" : 'Todas tus oportunidades tienen próxima acción'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /** @return array<int, string> oportunidades abiertas mías sin actividad pendiente */
    public function sinProximaAccion(): array
    {
        return Oportunidad::query()->where('ejecutivo_user_id', auth()->id())
            ->whereHas('estado', fn ($q) => $q->whereIn('codigo', ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion']))
            ->whereNotExists(fn ($q) => $q->from('actividades')->whereColumn('actividades.oportunidad_id', 'oportunidades.id')->where('es_realizada', false))
            ->orderBy('fecha_cierre_estimada')->pluck('nombre', 'id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            self::registrar(null, fn () => $this->resetTable())->label('Registrar actividad')->icon('heroicon-o-plus'),
            Action::make('programar')->label('Programar las que faltan')->icon('heroicon-o-bell-alert')->color('warning')
                ->visible(fn (): bool => $this->sinProximaAccion() !== [])
                ->modalHeading('Oportunidades sin próxima acción')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                ->modalContent(fn () => view('actividades::sin-proxima', ['oportunidades' => $this->sinProximaAccion()])),
        ];
    }

    /** El formulario rápido (v4: pensado para el celular, cuatro toques). Se usa aquí y en la ficha de la oportunidad. */
    public static function registrar(?int $oportunidadFija, \Closure $alTerminar): Action
    {
        return Action::make('registrarActividad')->slideOver()->modalHeading('Registrar actividad')
            ->visible(fn (): bool => auth()->user()?->can(ActividadesService::PERMISO_REGISTRAR) ?? false)
            ->fillForm(fn (): array => ['tipo' => 'llamada', 'fecha' => Calendario::ahora()->format('Y-m-d H:i'), 'relacion' => $oportunidadFija === null ? null : "o:{$oportunidadFija}", 'proxima' => 'tres_dias'])
            ->schema([
                ToggleButtons::make('tipo')->label('Tipo')->options(Actividad::TIPOS)->icons(Actividad::ICONOS)->inline()->required(),
                Select::make('relacion')->label('Relacionado con')->required()->searchable()->disabled($oportunidadFija !== null)->dehydrated()
                    ->options(fn (): array => self::relaciones()),
                TextInput::make('asunto')->label('Asunto')->required()->maxLength(200)->placeholder('Visita para revisar el alcance'),
                DateTimePicker::make('fecha')->label('Cuándo')->required()->seconds(false)
                    ->helperText('Si es futura, queda en la agenda como pendiente.'),
                Textarea::make('resultado')->label('Qué pasó')->rows(3),
                ToggleButtons::make('proxima')->label('Próxima acción')->inline()
                    ->options(['manana' => 'Mañana', 'tres_dias' => '3 días', 'semana' => '1 semana', 'ninguna' => 'Ninguna'])->required()->live(),
                TextInput::make('asunto_proxima')->label('¿Qué sigue?')->maxLength(200)->placeholder('Enviar la propuesta')
                    ->visible(fn (Get $get): bool => $get('proxima') !== 'ninguna'),
            ])
            ->action(function (array $data, Action $action) use ($alTerminar): void {
                [$clase, $id] = explode(':', (string) $data['relacion']);
                $oportunidadId = $clase === 'o' ? (int) $id : null;
                $cuentaId = $clase === 'c' ? (int) $id : (int) Oportunidad::query()->whereKey($oportunidadId)->value('cuenta_id');

                try {
                    /** @var User $u */
                    $u = auth()->user();
                    app(ActividadesService::class)->registrar($data['tipo'], $cuentaId, $oportunidadId, $data['asunto'], CarbonImmutable::parse($data['fecha'], 'America/Lima'),
                        $data['resultado'] ?? null, $data['proxima'] === 'ninguna' ? null : $data['proxima'], $data['asunto_proxima'] ?? null, $u);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Actividad registrada.')->send();
                $alTerminar();
            });
    }

    /** @return array<string, array<string, string>> oportunidades abiertas y cuentas, agrupadas */
    private static function relaciones(): array
    {
        $oportunidades = Oportunidad::query()->with('estado')->whereHas('estado', fn ($q) => $q->whereIn('codigo', ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion']))
            ->orderBy('nombre')->get()->mapWithKeys(fn (Oportunidad $o): array => ["o:{$o->id}" => "{$o->numero} · {$o->nombre}"])->all();
        $cuentas = DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->where('cuentas.es_activa', true)->orderBy('parties.razon_social')
            ->pluck('parties.razon_social', 'cuentas.id')->mapWithKeys(fn (string $n, int $id): array => ["c:{$id}" => $n])->all();

        return ['Oportunidades abiertas' => $oportunidades, 'Cuentas' => $cuentas];
    }

    public function table(Table $table): Table
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy(), 'America/Lima');

        return $table
            ->query(fn (): Builder => Actividad::query()->with(['cuenta.party', 'oportunidad'])->where('responsable_user_id', auth()->id()))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('es_realizada')->orderByRaw('CASE WHEN es_realizada THEN 0 ELSE EXTRACT(EPOCH FROM fecha) END')->orderByDesc('fecha'))
            ->columns([
                TextColumn::make('cuando')->label('Cuándo')
                    ->state(function (Actividad $record) use ($hoy): string {
                        $f = $record->fecha->setTimezone('America/Lima');

                        return match (true) {
                            $record->es_realizada => $f->format('d/m H:i'),
                            $f->lt($hoy) => 'Vencida · '.$f->format('d/m'),
                            $f->isSameDay($hoy) => 'Hoy · '.$f->format('H:i'),
                            default => $f->translatedFormat('D d/m').' · '.$f->format('H:i'),
                        };
                    })
                    ->color(fn (Actividad $record): ?string => ! $record->es_realizada && $record->fecha->lt($hoy) ? 'danger' : (! $record->es_realizada && $record->fecha->setTimezone('America/Lima')->isSameDay($hoy) ? 'warning' : null))
                    ->weight(fn (Actividad $record): ?string => $record->es_realizada ? null : 'bold'),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state): string => Actividad::TIPOS[$state])->icon(fn (string $state): string => Actividad::ICONOS[$state])->color('gray'),
                TextColumn::make('asunto')->label('Asunto')->wrap()
                    ->description(fn (Actividad $record): ?string => $record->resultado),
                TextColumn::make('relacion')->label('Relacionado con')->wrap()
                    ->state(fn (Actividad $record): string => $record->oportunidad?->nombre ?? (string) $record->cuenta?->party?->razon_social)
                    ->description(fn (Actividad $record): ?string => $record->oportunidad === null ? null : (string) $record->cuenta?->party?->razon_social)
                    ->url(fn (Actividad $record): ?string => $record->oportunidad_id === null ? null : VerOportunidad::getUrl(['record' => $record->oportunidad_id])),
            ])
            ->filters([
                SelectFilter::make('vista')->label('Ver')->default('pendientes')
                    ->options(['pendientes' => 'Pendientes', 'hechas' => 'Lo hecho', 'todas' => 'Todo'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'pendientes') {
                        'hechas' => $query->where('es_realizada', true),
                        'todas' => $query,
                        default => $query->where('es_realizada', false),
                    }),
            ])
            ->recordActions([
                Action::make('hecha')->label('Hecha')->icon('heroicon-o-check')->button()->color('success')
                    ->hidden(fn (Actividad $record): bool => $record->es_realizada)
                    ->schema([Textarea::make('resultado')->label('Qué pasó')->rows(3)])
                    ->action(function (Actividad $record, array $data): void {
                        app(ActividadesService::class)->cumplir($record->id, $data['resultado'] ?? null);
                        Notification::make()->success()->title('Hecha. ¿Programas la siguiente?')->send();
                    }),
            ])
            ->emptyStateHeading('Nada pendiente')
            ->emptyStateDescription('Registra lo que hiciste y programa lo que sigue.');
    }
}
