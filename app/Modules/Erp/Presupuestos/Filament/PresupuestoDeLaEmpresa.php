<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Services\PresupuestoEmpresaService;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t2-presupuesto-empresa): el presupuesto de la empresa del año: sus líneas por sede y rubro con los
 * doce meses, el comparativo por sede y mes con sus alertas, y los botones Registrar gasto directo, Traspasar y Aprobar.
 */
final class PresupuestoDeLaEmpresa extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'presupuesto-empresa';

    protected static ?string $title = 'Presupuesto de la empresa';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    public int $anio = 2026;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PresupuestoEmpresaService::PERMISO_VER);
    }

    private function servicio(): PresupuestoEmpresaService
    {
        return app(PresupuestoEmpresaService::class);
    }

    private function presupuesto(): ?object
    {
        return DB::table('presupuestos_empresa')->where('anio', $this->anio)->first();
    }

    public function getSubheading(): string
    {
        $p = $this->presupuesto();

        return $p === null ? "Sin presupuesto para {$this->anio}." : ($p->estado === 'aprobado' ? 'Aprobado (base) el '.CarbonImmutable::parse($p->aprobado_at)->format('d/m/Y').' · los cambios van por traspasos' : 'En borrador')
            .' · Controla el gasto directo por sede y mes; los gastos generales de cada proyecto se quedan en su árbol.';
    }

    public function content(Schema $schema): Schema
    {
        $hasta = $this->anio < (int) Calendario::ahora()->format('Y') ? 12 : (int) Calendario::ahora()->format('n');

        return $schema->components([
            EmbeddedTable::make(),
            View::make('presupuestos::empresa.comparativo')->viewData(['filas' => $this->servicio()->comparativo($this->anio, $hasta)]),
        ]);
    }

    public function table(Table $table): Table
    {
        $sedes = $this->servicio()->sedes();
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
        $columnas = [
            TextColumn::make('sede')->label('Sede')->state(fn (array $record): string => $sedes[$record['sede_id'] ?? 'empresa'] ?? '—')->weight('bold')
                ->description(fn (array $record): string => $record['rubro']),
        ];

        foreach ($meses as $i => $mes) {
            $col = sprintf('m%02d', $i + 1);
            $columnas[] = TextColumn::make($col)->label($mes)->alignEnd()->fontFamily('mono')->size('xs')->formatStateUsing(fn ($state): string => number_format((float) $state, 0));
        }

        $columnas[] = TextColumn::make('anio')->label('Año')->alignEnd()->fontFamily('mono')->weight('bold')
            ->state(fn (array $record): string => number_format(array_sum(array_map(fn (int $m): float => (float) $record[sprintf('m%02d', $m)], range(1, 12))), 2));

        return $table
            ->records(fn (): array => DB::table('presupuesto_empresa_lineas')->where('presupuesto_empresa_id', $this->presupuesto()?->id ?? 0)->orderByRaw('sede_id NULLS LAST')->orderBy('rubro')->get()
                ->map(fn (object $l): array => ['__key' => (string) $l->id, ...(array) $l])->all())
            ->paginated(false)
            ->heading("Presupuesto {$this->anio} por sede y rubro (S/)")
            ->columns($columnas)
            ->emptyStateHeading('Sin líneas');
    }

    protected function getHeaderActions(): array
    {
        $gestiona = fn (): bool => auth()->user()?->can(PresupuestoEmpresaService::PERMISO_GESTIONAR) ?? false;
        $sedes = fn (): array => $this->servicio()->sedes();
        $lineas = fn (): array => DB::table('presupuesto_empresa_lineas')->where('presupuesto_empresa_id', $this->presupuesto()?->id ?? 0)->get()
            ->mapWithKeys(fn (object $l): array => [$l->id => ($sedes()[$l->sede_id ?? 'empresa'] ?? '—').' · '.$l->rubro])->all();
        $meses = array_combine(range(1, 12), ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre']);

        return [
            Action::make('gasto')->label('Registrar gasto directo')->icon('heroicon-o-banknotes')->visible($gestiona)->slideOver()
                ->modalDescription('Un gasto de la empresa, no de un proyecto: con su sede (obligatoria), su rubro y su centro de costo. Se compara con el saldo de la sede en el mes.')
                ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
                ->schema([
                    Select::make('sede')->label('Sede')->options($sedes)->required(),
                    Select::make('rubro')->label('Rubro')->options(array_combine(PresupuestoEmpresaService::RUBROS, PresupuestoEmpresaService::RUBROS))->required(),
                    Select::make('centro_costo_id')->label('Centro de costo')->options(fn (): array => DB::table('centros_costo')->orderBy('nombre')->pluck('nombre', 'id')->all())->required(),
                    TextInput::make('descripcion')->label('Descripción')->required()->maxLength(200),
                    TextInput::make('proveedor')->label('Proveedor')->required()->maxLength(150),
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                    Grid::make(2)->schema([
                        TextInput::make('monto')->label('Comprometido (S/)')->numeric()->required()->minValue(0.01),
                        TextInput::make('ejecutado')->label('Ejecutado (S/)')->numeric()->default(0),
                    ]),
                    TextInput::make('motivo')->label('Motivo del exceso (si pasa el saldo)')->maxLength(300),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $this->servicio()->gastoDirecto($data['sede'] === 'empresa' ? null : (int) $data['sede'], $data['rubro'], (int) $data['centro_costo_id'], CarbonImmutable::parse($data['fecha']),
                            (string) $data['monto'], (string) ($data['ejecutado'] ?? '0'), $data['descripcion'], $data['proveedor'], $data['motivo'] ?? null);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title('Gasto registrado.')->send();
                }),
            Action::make('traspasar')->label('Traspasar')->icon('heroicon-o-arrows-right-left')->color('gray')->visible($gestiona)
                ->modalDescription('Mueve saldo presupuestado de una sede o un mes a otro, con su motivo. Distinto de reclasificar un gasto ya hecho.')
                ->schema([
                    Select::make('desde')->label('Desde')->options($lineas)->required(),
                    Select::make('desde_mes')->label('Mes de origen')->options($meses)->required(),
                    Select::make('hacia')->label('Hacia')->options($lineas)->required(),
                    Select::make('hacia_mes')->label('Mes de destino')->options($meses)->required(),
                    TextInput::make('monto')->label('Monto (S/)')->numeric()->required()->minValue(0.01),
                    TextInput::make('motivo')->label('Motivo')->required()->maxLength(300),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->servicio()->traspasar((int) $data['desde'], (int) $data['desde_mes'], (int) $data['hacia'], (int) $data['hacia_mes'], (string) $data['monto'], $data['motivo']);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Traspaso hecho.')->send();
                }),
            Action::make('aprobar')->label('Aprobar')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => $gestiona() && $this->presupuesto()?->estado === 'borrador')
                ->modalDescription('La versión aprobada es la base: desde ahí controla el gasto y los cambios van por traspasos.')
                ->action(fn () => $this->servicio()->aprobar((int) $this->presupuesto()?->id)),
        ];
    }
}
