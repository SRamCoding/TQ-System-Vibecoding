<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Filament\Pages;

use App\Modules\Erp\Personal\Models\Colaborador;
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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t4-personal-tareo): el tablero de asignación por persona y semana; marca la sobreasignación antes del conflicto. */
final class Asignacion extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'asignacion';

    protected static ?string $title = 'Personal asignado';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PersonalService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        return 'Cada persona con su dedicación por proyecto y semana. Más de 100 % es sobreasignación. Solo se tarea en obra a quien tiene su habilitación (SCTR) vigente.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /** @return list<CarbonImmutable> el lunes de esta semana y las tres siguientes */
    private function semanas(): array
    {
        $lunes = CarbonImmutable::parse(Calendario::hoy())->startOfWeek();

        return [$lunes, $lunes->addWeek(), $lunes->addWeeks(2), $lunes->addWeeks(3)];
    }

    public function table(Table $table): Table
    {
        $s = app(PersonalService::class);
        $columnas = [
            TextColumn::make('nombres')->label('Persona')->weight('bold')->description(fn (array $record): string => $record['categoria'].' · '.$record['vinculo']),
            TextColumn::make('sctr')->label('Habilitación')->color(fn (array $record): ?string => $record['vencida'] ? 'danger' : null),
        ];

        foreach ($this->semanas() as $i => $lunes) {
            $columnas[] = TextColumn::make("s{$i}")->label('Sem '.$lunes->isoWeek().' · '.$lunes->format('d/m'))->wrap()
                ->color(fn (array $record): ?string => $record["t{$i}"] > 100 ? 'danger' : ($record["t{$i}"] === 0 ? 'gray' : null))
                ->description(fn (array $record): ?string => $record["t{$i}"] > 100 ? $record["t{$i}"].' % · sobreasignado' : null);
        }

        return $table
            ->records(function (): array {
                $filas = [];

                foreach (Colaborador::query()->where('es_activo', true)->orderBy('nombres')->get() as $c) {
                    $fila = ['__key' => (string) $c->id, 'nombres' => $c->nombres, 'categoria' => (string) DB::table('categorias_personal')->where('id', $c->categoria_id)->value('nombre'),
                        'vinculo' => $c->vinculo, 'sctr' => $c->habilitado_hasta === null ? 'sin SCTR' : 'SCTR hasta '.$c->habilitado_hasta->format('d/m/Y'),
                        'vencida' => $c->habilitado_hasta === null || $c->habilitado_hasta->lt(CarbonImmutable::parse(Calendario::hoy()))];

                    foreach ($this->semanas() as $i => $lunes) {
                        $partes = DB::table('asignaciones as a')->join('proyectos as p', 'p.id', '=', 'a.proyecto_id')->where('a.colaborador_id', $c->id)
                            ->where('a.desde', '<=', $lunes->addDays(6)->toDateString())->where(fn ($q) => $q->whereNull('a.hasta')->orWhere('a.hasta', '>=', $lunes->toDateString()))
                            ->get(['p.codigo', 'a.porcentaje']);
                        $fila["s{$i}"] = $partes->isEmpty() ? 'sin asignar' : $partes->map(fn ($p): string => "{$p->codigo} {$p->porcentaje} %")->implode(' · ');
                        $fila["t{$i}"] = (int) $partes->sum('porcentaje');
                    }

                    $filas[] = $fila;
                }

                return $filas;
            })
            ->paginated(false)
            ->columns($columnas);
    }

    protected function getHeaderActions(): array
    {
        $puede = fn (): bool => auth()->user()?->can(PersonalService::PERMISO_TAREAR) ?? false;

        return [
            Action::make('colaborador')->label('Nuevo colaborador')->icon('heroicon-o-user-plus')->color('gray')->visible($puede)
                ->schema([
                    TextInput::make('nombres')->label('Nombres y apellidos')->required()->maxLength(120),
                    TextInput::make('dni')->label('DNI')->required()->length(8),
                    Select::make('categoria_id')->label('Categoría')->required()->options(fn (): array => DB::table('categorias_personal')->pluck('nombre', 'id')->all()),
                    Select::make('vinculo')->label('Vínculo')->required()->options(['planilla' => 'Planilla', 'eventual' => 'Eventual']),
                    DatePicker::make('habilitado_hasta')->label('SCTR vigente hasta'),
                ])
                ->action(fn (array $data) => Colaborador::query()->create($data)),
            Action::make('asignar')->label('Asignar')->icon('heroicon-o-plus')->visible($puede)
                ->fillForm(fn (): array => ['desde' => Calendario::hoy(), 'porcentaje' => 100])
                ->schema([
                    Select::make('colaborador_id')->label('Persona')->required()->options(fn (): array => Colaborador::query()->where('es_activo', true)->orderBy('nombres')->pluck('nombres', 'id')->all()),
                    Select::make('proyecto_id')->label('Proyecto')->required()->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->whereIn('e.codigo', ['en_preparacion', 'en_ejecucion'])->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                    TextInput::make('porcentaje')->label('Dedicación (%)')->numeric()->integer()->required(),
                    DatePicker::make('desde')->label('Desde')->required(),
                    DatePicker::make('hasta')->label('Hasta'),
                ])
                ->action(function (array $data): void {
                    try {
                        $s = app(PersonalService::class);
                        $s->asignar((int) $data['colaborador_id'], (int) $data['proyecto_id'], (int) $data['porcentaje'], CarbonImmutable::parse($data['desde']), filled($data['hasta'] ?? null) ? CarbonImmutable::parse($data['hasta']) : null);
                        $total = $s->dedicacion((int) $data['colaborador_id'], CarbonImmutable::parse($data['desde']));
                        Notification::make()->{$total > 100 ? 'warning' : 'success'}()->title($total > 100 ? "Asignado, pero queda sobreasignado al {$total} %." : 'Asignado.')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
