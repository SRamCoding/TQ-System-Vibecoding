<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\Proceso;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * MVP rápido (ficha op-asistente): los tickets que propuso el asistente y la persona confirmó (soporte los atiende).
 * MV-040: una franja fija de filtros arriba de la lista: el estado como pestañas con contador (Pendientes al entrar), el
 * buscador y filtros en botones (proceso, quién lo abrió, fecha, esperando más de…). Los filtros se recuerdan por persona.
 */
final class TicketsAyuda extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'tickets-ayuda';

    protected static ?string $title = 'Tickets de ayuda';

    protected static string|BackedEnum|null $navigationIcon = 'tq-ticket-soporte';

    protected static bool $shouldRegisterNavigation = false;

    public const array ESTADOS = ['sin_atender' => 'Sin atender', 'en_curso' => 'En curso', 'resuelto' => 'Resuelto'];

    public const array PESTANAS = ['pendientes' => 'Pendientes', 'sin_atender' => 'Sin atender', 'en_curso' => 'En curso', 'resuelto' => 'Resueltos', 'todos' => 'Todos'];

    public const array FECHAS = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'rango' => 'Rango'];

    public string $pestana = 'pendientes';

    public string $buscar = '';

    public ?string $proceso = null;

    public ?string $quien = null;

    public ?string $fecha = null;

    public ?string $desde = null;

    public ?string $hasta = null;

    public ?int $esperando = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    private static function soporte(): bool
    {
        return auth()->user()?->can(RolesPermisos::PERMISO) ?? false;
    }

    /** Los filtros de la persona se recuerdan al salir y volver (en su sesión). */
    public function mount(): void
    {
        foreach ((array) session('tq.tickets_ayuda.filtros', []) as $k => $v) {
            if (in_array($k, ['pestana', 'buscar', 'proceso', 'quien', 'fecha', 'desde', 'hasta', 'esperando'], true)) {
                $this->{$k} = $v;
            }
        }
    }

    public function updated(): void
    {
        $this->recordar();
    }

    public function poner(string $filtro, mixed $valor): void
    {
        if (in_array($filtro, ['pestana', 'proceso', 'quien', 'fecha', 'esperando'], true)) {
            $this->{$filtro} = $filtro === 'esperando' ? ($valor === null ? null : (int) $valor) : $valor;
            $this->recordar();
        }
    }

    public function limpiar(): void
    {
        $this->buscar = '';
        $this->proceso = $this->quien = $this->fecha = $this->desde = $this->hasta = null;
        $this->esperando = null;
        $this->recordar();
    }

    private function recordar(): void
    {
        session(['tq.tickets_ayuda.filtros' => ['pestana' => $this->pestana, 'buscar' => $this->buscar, 'proceso' => $this->proceso, 'quien' => $this->quien,
            'fecha' => $this->fecha, 'desde' => $this->desde, 'hasta' => $this->hasta, 'esperando' => $this->esperando]]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.tickets-ayuda.filtros')->viewData(fn (): array => $this->datosFiltros()),
            EmbeddedTable::make(),
        ]);
    }

    /** @return array<string, mixed> */
    private function datosFiltros(): array
    {
        $todos = $this->tickets(sinEstado: true);
        $contar = fn (array $estados): int => count(array_filter($todos, fn (array $t): bool => in_array($t['estado'], $estados, true)));

        return [
            'cuentas' => ['pendientes' => $contar(['sin_atender', 'en_curso']), 'sin_atender' => $contar(['sin_atender']), 'en_curso' => $contar(['en_curso']), 'resuelto' => $contar(['resuelto']), 'todos' => count($todos)],
            'procesos' => array_combine(array_map(fn (Proceso $p): string => $p->value, array_filter(Proceso::cases(), fn (Proceso $p): bool => $p !== Proceso::Mien)), array_map(fn (Proceso $p): string => $p->nombre(), array_filter(Proceso::cases(), fn (Proceso $p): bool => $p !== Proceso::Mien))),
            'personas' => self::soporte() ? DB::table('tickets_ayuda as t')->join('users as u', 'u.id', '=', 't.user_id')->distinct()->orderBy('u.name')->pluck('u.name', 'u.id')->all() : [],
            'soporte' => self::soporte(),
            'hayFiltros' => $this->buscar !== '' || $this->proceso !== null || $this->quien !== null || $this->fecha !== null || $this->esperando !== null,
        ];
    }

    /**
     * Los tickets que ve la persona, ya filtrados y en su orden: los pendientes del más antiguo al más nuevo (se atienden
     * por turno) y los resueltos del más nuevo al más antiguo.
     *
     * @return list<array<string, mixed>>
     */
    private function tickets(bool $sinEstado = false): array
    {
        $navegacion = app(NavegacionService::class);
        $ahora = Calendario::ahora();
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $q = mb_strtolower(trim($this->buscar));

        $filas = DB::table('tickets_ayuda as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->when(! self::soporte(), fn ($c) => $c->where('t.user_id', auth()->id()))
            ->get(['t.*', 'u.name'])
            ->map(function (object $r) use ($navegacion, $ahora): array {
                $proceso = $navegacion->procesoDeRutaGeneral($r->pantalla);
                $creado = CarbonImmutable::parse($r->created_at);

                return ['__key' => (string) $r->id, ...(array) $r, 'proceso' => $proceso?->value, 'proceso_nombre' => $proceso?->nombre() ?? 'Sin proceso',
                    'creado' => $creado, 'espera' => $r->estado === 'resuelto' ? null : (int) $creado->diffInDays($ahora)];
            })
            ->filter(function (array $t) use ($sinEstado, $q, $hoy): bool {
                if (! $sinEstado && $this->pestana !== 'todos' && ! in_array($t['estado'], $this->pestana === 'pendientes' ? ['sin_atender', 'en_curso'] : [$this->pestana], true)) {
                    return false;
                }

                if ($q !== '' && ! str_contains(mb_strtolower($t['codigo'].' '.$t['resumen'].' '.($t['conversacion'] ?? '')), $q)) {
                    return false;
                }

                if ($this->proceso !== null && $t['proceso'] !== $this->proceso) {
                    return false;
                }

                if ($this->quien !== null && (string) $t['user_id'] !== (string) $this->quien) {
                    return false;
                }

                if ($this->esperando !== null && ($t['espera'] === null || $t['espera'] < $this->esperando)) {
                    return false;
                }

                $dia = $t['creado']->startOfDay();

                return match ($this->fecha) {
                    'hoy' => $dia->equalTo($hoy),
                    'semana' => $dia->gte($hoy->startOfWeek()),
                    'mes' => $dia->gte($hoy->startOfMonth()),
                    'rango' => ($this->desde === null || $dia->gte(CarbonImmutable::parse($this->desde))) && ($this->hasta === null || $dia->lte(CarbonImmutable::parse($this->hasta))),
                    default => true,
                };
            })
            ->values()->all();

        usort($filas, fn (array $a, array $b): int => ($a['estado'] === 'resuelto') <=> ($b['estado'] === 'resuelto')
            ?: ($a['estado'] === 'resuelto' ? $b['creado'] <=> $a['creado'] : $a['creado'] <=> $b['creado']));

        return $filas;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->tickets())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->description(fn (array $record): string => $record['creado']->format('d/m/Y H:i')),
                TextColumn::make('resumen')->label('Problema')->wrap(),
                TextColumn::make('proceso_nombre')->label('Proceso y pantalla')->description(fn (array $record): string => $record['pantalla']),
                TextColumn::make('name')->label('Quién lo abrió'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => ['sin_atender' => 'warning', 'en_curso' => 'info', 'resuelto' => 'success'][$state] ?? 'gray'),
                TextColumn::make('espera')->label('Esperando')->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : ($state === 0 ? 'hoy' : ($state === 1 ? '1 día' : "{$state} días")))
                    ->color(fn (array $record): ?string => ($record['espera'] ?? 0) >= 2 ? 'warning' : null),
            ])
            ->recordActions([
                Action::make('avanzar')->label(fn (array $record): string => $record['estado'] === 'sin_atender' ? 'Tomar' : 'Resolver')->button()->color('gray')
                    ->hidden(fn (array $record): bool => $record['estado'] === 'resuelto' || ! self::soporte())
                    ->action(fn (array $record) => DB::table('tickets_ayuda')->where('id', $record['id'])->update(['estado' => $record['estado'] === 'sin_atender' ? 'en_curso' : 'resuelto', 'updated_at' => now()])),
                Action::make('ver')->label('Conversación')->color('gray')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (array $record) => new HtmlString('<pre style="white-space:pre-wrap;font-size:13px">'.e($record['conversacion'] ?? '—').'</pre>')),
            ])
            ->emptyStateHeading('Sin tickets con estos filtros');
    }
}
