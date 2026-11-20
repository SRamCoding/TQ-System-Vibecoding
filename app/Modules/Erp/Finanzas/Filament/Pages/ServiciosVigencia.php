<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t5-finanzas v3): servicios con vigencia (SCTR, pólizas, alquileres, licencias), con su aviso de vencimiento. */
final class ServiciosVigencia extends Base implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'servicios-vigencia';

    protected static ?string $title = 'Servicios con vigencia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public const array TIPOS = ['sctr' => 'SCTR', 'poliza' => 'Póliza de seguro', 'alquiler' => 'Alquiler', 'licencia' => 'Licencia de software', 'suscripcion' => 'Suscripción en la nube'];

    public function getSubheading(): string
    {
        $pronto = DB::table('servicios_vigencia')->where('hasta', '<=', CarbonImmutable::parse(Calendario::hoy())->addDays(30)->toDateString())->count();

        return ($pronto > 0 ? "{$pronto} vencen en 30 días o menos · " : '').'Cada periodo genera su orden de servicio (en los gastos generales del proyecto o en su centro de costo). Avisa a 60, 30 y 15 días.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return $table
            ->records(fn (): array => DB::table('servicios_vigencia as s')->leftJoin('proyectos as p', 'p.id', '=', 's.proyecto_id')->orderBy('s.hasta')->get(['s.*', 'p.codigo as pry'])
                ->map(fn (object $r): array => ['__key' => (string) $r->id, ...(array) $r, 'dias' => (int) $hoy->diffInDays(CarbonImmutable::parse($r->hasta), false)])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('descripcion')->label('Servicio')->weight('bold')->description(fn (array $record): string => self::TIPOS[$record['tipo']].' · '.$record['proveedor']),
                TextColumn::make('pry')->label('Proyecto')->placeholder('Empresa'),
                TextColumn::make('monto_periodo')->label('Monto')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state, array $record): string => self::s($state).' '.['mensual' => 'al mes', 'anual' => 'al año', 'unico' => 'único'][$record['periodicidad']]),
                TextColumn::make('hasta')->label('Vigente hasta')->date('d/m/Y')
                    ->description(fn (array $record): string => $record['dias'] < 0 ? 'vencido hace '.abs($record['dias']).' días' : "vence en {$record['dias']} días")
                    ->color(fn (array $record): ?string => $record['dias'] <= 15 ? 'danger' : ($record['dias'] <= 30 ? 'warning' : ($record['dias'] <= 60 ? 'info' : null))),
                TextColumn::make('ultima_orden')->label('Última orden')->date('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('orden')->label('Orden del periodo')->button()->color('gray')->visible(fn (): bool => self::puede(FinanzasService::PERMISO_PAGAR))
                    ->action(fn (array $record) => $this->hacer(fn () => app(FinanzasService::class)->ordenDelPeriodo((int) $record['id'], CarbonImmutable::parse(Calendario::hoy())), 'Orden de servicio del periodo aprobada.')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevo')->label('Nuevo servicio')->icon('heroicon-o-plus')->visible(fn (): bool => self::puede(FinanzasService::PERMISO_PAGAR))
                ->schema([
                    Select::make('tipo')->label('Tipo')->options(self::TIPOS)->required(),
                    TextInput::make('proveedor')->label('Proveedor')->required(),
                    TextInput::make('descripcion')->label('Descripción')->required(),
                    Select::make('proyecto')->label('Proyecto (vacío: la empresa)')->options(fn (): array => DB::table('proyectos')->orderBy('codigo')->pluck('codigo', 'id')->all()),
                    Select::make('periodicidad')->label('Periodicidad')->options(['mensual' => 'Mensual', 'anual' => 'Anual', 'unico' => 'Único'])->required(),
                    TextInput::make('monto')->label('Monto del periodo')->numeric()->required(),
                    DatePicker::make('desde')->label('Desde')->required()->format('Y-m-d'),
                    DatePicker::make('hasta')->label('Hasta')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => app(FinanzasService::class)->crearServicio($data['tipo'], $data['proveedor'], $data['descripcion'], filled($data['proyecto'] ?? null) ? (int) $data['proyecto'] : null, null,
                    $data['periodicidad'], (string) $data['monto'], $data['desde'], $data['hasta'])),
        ];
    }
}
