<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Models\Herramienta;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Erp\Compras\Services\HerramientasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
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
 * MV-028: las herramientas propias (taladros, escaleras, la camioneta, equipos de medición), junto al almacén: qué
 * herramienta está en qué obra, quién la tiene y cuándo debe volver. Las atrasadas, en rojo.
 */
final class Herramientas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'herramientas';

    protected static ?string $title = 'Herramientas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    public static function canAccess(): bool
    {
        return Compras::puede(ComprasService::PERMISO_VER);
    }

    public function getSubheading(): string
    {
        $hoy = Calendario::hoy();
        $enObra = Herramienta::query()->where('estado', 'en_obra')->count();
        $atrasadas = Herramienta::query()->where('estado', 'en_obra')->where('debe_volver', '<', $hoy)->count();

        return Herramienta::query()->count()." herramientas · {$enObra} en obra · ".($atrasadas === 0 ? 'ninguna atrasada' : "{$atrasadas} debieron volver y no volvieron").'.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return $table
            ->query(fn (): Builder => Herramienta::query()
                ->leftJoin('proyectos', 'proyectos.id', '=', 'herramientas.proyecto_id')
                ->leftJoin('colaboradores', 'colaboradores.id', '=', 'herramientas.colaborador_id')
                ->select('herramientas.*', 'proyectos.codigo as proyecto_codigo', 'proyectos.nombre as proyecto_nombre', 'colaboradores.nombres as quien'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw("herramientas.estado <> 'en_obra'")->orderBy('herramientas.debe_volver')->orderBy('herramientas.codigo'))
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable(),
                TextColumn::make('nombre')->label('Herramienta')->searchable()->wrap()
                    ->description(fn (Herramienta $record): string => Herramienta::TIPOS[$record->tipo].($record->marca_modelo ? ' · '.$record->marca_modelo : '').($record->serie ? ' · serie '.$record->serie : '')),
                TextColumn::make('donde')->label('Dónde está')->state(fn (Herramienta $record): string => $record->estado === 'en_obra' ? (string) $record->getAttribute('proyecto_codigo') : Herramienta::ESTADOS[$record->estado])
                    ->description(fn (Herramienta $record): ?string => $record->estado === 'en_obra' ? $record->getAttribute('proyecto_nombre') : null)->wrap(),
                TextColumn::make('quien')->label('Quién la tiene')->placeholder('—'),
                TextColumn::make('debe_volver')->label('Debe volver')->date('d/m/Y')->placeholder('—')
                    ->description(fn (Herramienta $record): ?string => $record->debe_volver === null ? null : ($record->debe_volver->lt($hoy) ? 'atrasada '.(int) $record->debe_volver->diffInDays($hoy).' d' : 'en '.(int) $hoy->diffInDays($record->debe_volver).' d'))
                    ->color(fn (Herramienta $record): ?string => $record->debe_volver?->lt($hoy) ? 'danger' : null),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => Herramienta::ESTADOS[$state])
                    ->color(fn (string $state): string => match ($state) {
                        'en_obra' => 'info', 'en_almacen' => 'success', 'en_mantenimiento' => 'warning', default => 'gray'
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(Herramienta::ESTADOS),
                SelectFilter::make('tipo')->label('Tipo')->options(Herramienta::TIPOS),
            ])
            ->recordActions([
                Action::make('enviar')->label('Enviar a obra')->button()->color('info')
                    ->hidden(fn (Herramienta $record): bool => $record->estado !== 'en_almacen' || ! Compras::puede(ComprasService::PERMISO_COMPRAR))
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'vuelve' => CarbonImmutable::parse(Calendario::hoy())->addDays(14)->toDateString()])
                    ->schema([
                        Select::make('proyecto')->label('Proyecto')->required()->searchable()
                            ->options(fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->whereIn('e.codigo', ['en_ejecucion', 'en_preparacion'])->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all()),
                        Select::make('colaborador')->label('Quién la lleva')->required()->searchable()
                            ->options(fn (): array => DB::table('colaboradores')->where('es_activo', true)->orderBy('nombres')->pluck('nombres', 'id')->all()),
                        DatePicker::make('fecha')->label('Sale el')->required()->format('Y-m-d'),
                        DatePicker::make('vuelve')->label('Debe volver el')->required()->format('Y-m-d'),
                    ])
                    ->action(fn (Herramienta $record, array $data) => $this->hacer(fn () => app(HerramientasService::class)->enviarAObra($record->id, (int) $data['proyecto'], (int) $data['colaborador'],
                        CarbonImmutable::parse($data['fecha']), CarbonImmutable::parse($data['vuelve']), $this->usuario()), 'Salió a obra.')),
                Action::make('devolver')->label('Volvió')->button()->color('success')
                    ->hidden(fn (Herramienta $record): bool => ! in_array($record->estado, ['en_obra', 'en_mantenimiento'], true) || ! Compras::puede(ComprasService::PERMISO_COMPRAR))
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'mantenimiento' => false])
                    ->schema([
                        DatePicker::make('fecha')->label('Volvió el')->required()->format('Y-m-d'),
                        Toggle::make('mantenimiento')->label('Pasa a mantenimiento'),
                        TextInput::make('nota')->label('Nota')->maxLength(250),
                    ])
                    ->action(fn (Herramienta $record, array $data) => $this->hacer(fn () => app(HerramientasService::class)->devolver($record->id, CarbonImmutable::parse($data['fecha']), (bool) $data['mantenimiento'], $this->usuario(), $data['nota'] ?? null), 'Registrado.')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nueva')->label('Nueva herramienta')->icon('heroicon-o-plus')->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_COMPRAR))
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(150)->placeholder('Taladro percutor'),
                    Select::make('tipo')->label('Tipo')->required()->options(Herramienta::TIPOS),
                    TextInput::make('marca')->label('Marca y modelo')->maxLength(120),
                    TextInput::make('serie')->label('Serie o placa')->maxLength(60),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(HerramientasService::class)->registrar($data['nombre'], $data['tipo'], $data['marca'] ?? null, $data['serie'] ?? null), 'Herramienta registrada en el almacén.')),
        ];
    }

    private function usuario(): ?User
    {
        $u = auth()->user();

        return $u instanceof User ? $u : null;
    }

    private function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
            Notification::make()->success()->title($exito)->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
