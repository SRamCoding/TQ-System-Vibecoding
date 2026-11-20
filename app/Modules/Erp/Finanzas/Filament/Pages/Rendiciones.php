<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Finanzas\Models\Fondo;
use App\Modules\Erp\Finanzas\Models\FondoGasto;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Finanzas\Services\LectorDeNotas;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t5-finanzas): fondos por proyecto (caja chica y rendiciones) y sus gastos con comprobante. */
final class Rendiciones extends Base implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'rendiciones';

    protected static ?string $title = 'Fondos y rendiciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    public function getSubheading(): string
    {
        return Fondo::query()->where('estado', 'abierto')->get()->map(function (Fondo $f): string {
            $g = (string) (FondoGasto::query()->where('fondo_id', $f->id)->where('estado', '!=', 'observado')->sum('monto') ?: '0');

            return "{$f->codigo} {$f->responsable}: entregado ".self::s($f->monto).', rendido '.self::s($g).', saldo '.self::s(bcsub((string) $f->monto, $g, 2));
        })->implode(' · ') ?: 'Sin fondos abiertos.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FondoGasto::query())
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fondo')->label('Fondo')->state(fn (FondoGasto $record): string => (string) Fondo::query()->whereKey($record->fondo_id)->value('codigo'))
                    ->description(fn (FondoGasto $record): string => (string) Fondo::query()->whereKey($record->fondo_id)->value('responsable')),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('descripcion')->label('Gasto')->wrap()->description(fn (FondoGasto $record): ?string => ($record->comprobante ?? '').($record->leido_por_ia ? ' · leído de la foto por IA, revisar' : '')),
                TextColumn::make('imputacion')->label('Imputación')->state(fn (FondoGasto $record): string => Compras::imputacion($record->partida_control_id)),
                TextColumn::make('monto')->label('Monto')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => self::s($state)),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ['por_aprobar' => 'Por aprobar', 'aprobado' => 'Aprobado', 'observado' => 'Observado'][$state])
                    ->color(fn (string $state): string => $state === 'aprobado' ? 'success' : ($state === 'observado' ? 'danger' : 'warning')),
            ])
            ->recordActions([
                Action::make('aprobar')->label('Aprobar')->button()->color('success')->hidden(fn (FondoGasto $record): bool => $record->estado !== 'por_aprobar')
                    ->action(fn (FondoGasto $record) => $this->hacer(fn () => app(FinanzasService::class)->aprobarGasto($record->id), 'Aprobado: es costo de su partida.')),
                Action::make('observar')->label('Observar')->color('gray')->hidden(fn (FondoGasto $record): bool => $record->estado !== 'por_aprobar')
                    ->action(fn (FondoGasto $record) => $record->update(['estado' => 'observado'])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $proyectos = fn (): array => DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('e.codigo', 'en_ejecucion')->selectRaw("p.id, p.codigo || ' · ' || p.nombre AS t")->pluck('t', 'p.id')->all();

        return [
            Action::make('fondo')->label('Entregar fondo')->icon('heroicon-o-banknotes')->color('gray')->visible(fn (): bool => self::puede(FinanzasService::PERMISO_PAGAR))
                ->fillForm(fn (): array => ['tipo' => 'rendicion', 'fecha' => Calendario::hoy()])
                ->schema([
                    Select::make('proyecto')->label('Proyecto')->required()->options($proyectos),
                    Select::make('tipo')->label('Tipo')->options(['rendicion' => 'Rendición (viáticos, compras menores)', 'caja_chica' => 'Caja chica de obra'])->required(),
                    TextInput::make('responsable')->label('Responsable')->required(),
                    TextInput::make('monto')->label('Monto')->numeric()->required(),
                    DatePicker::make('fecha')->label('Entregado el')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(FinanzasService::class)->crearFondo((int) $data['proyecto'], $data['tipo'], $data['responsable'], (string) $data['monto'], CarbonImmutable::parse($data['fecha'])), 'Fondo entregado.')),
            Action::make('gasto')->label('Registrar gasto')->icon('heroicon-o-camera')->visible(fn (): bool => Fondo::query()->where('estado', 'abierto')->exists())
                ->modalDescription('Con «leído de la foto por IA» la nota queda marcada para revisar: la IA sugiere y una persona confirma.')
                ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
                ->schema([
                    Select::make('fondo')->label('Fondo')->required()->live()->options(fn (): array => Fondo::query()->where('estado', 'abierto')->get()->mapWithKeys(fn (Fondo $f): array => [$f->id => "{$f->codigo} · {$f->responsable}"])->all()),
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                    TextInput::make('descripcion')->label('Gasto')->required(),
                    TextInput::make('comprobante')->label('Comprobante')->placeholder('B001-000123'),
                    TextInput::make('monto')->label('Monto')->numeric()->required(),
                    Select::make('partida')->label('Partida')->required()->options(fn ($get): array => Compras::partidasDe(is_numeric($get('fondo')) ? (int) Fondo::query()->whereKey((int) $get('fondo'))->value('proyecto_id') : null)),
                    Toggle::make('ia')->label('Leído de la foto por IA'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(FinanzasService::class)->registrarGasto((int) $data['fondo'], CarbonImmutable::parse($data['fecha']), $data['descripcion'], $data['comprobante'] ?? null, (string) $data['monto'], (int) $data['partida'], (bool) ($data['ia'] ?? false)), 'Gasto registrado.')),
            Action::make('foto')->label('Leer nota por foto')->icon('heroicon-o-sparkles')->color('gray')->visible(fn (): bool => Fondo::query()->where('estado', 'abierto')->exists())
                ->modalDescription('La IA lee la foto y deja el gasto por aprobar, marcado para revisar: la IA sugiere y una persona confirma. Sin la IA configurada, se escribe a mano.')
                ->schema([
                    Select::make('fondo')->label('Fondo')->required()->live()->options(fn (): array => Fondo::query()->where('estado', 'abierto')->get()->mapWithKeys(fn (Fondo $f): array => [$f->id => "{$f->codigo} · {$f->responsable}"])->all()),
                    Select::make('partida')->label('Partida')->required()->options(fn ($get): array => Compras::partidasDe(is_numeric($get('fondo')) ? (int) Fondo::query()->whereKey((int) $get('fondo'))->value('proyecto_id') : null)),
                    FileUpload::make('foto')->label('Foto de la nota')->image()->required()->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $leido = app(LectorDeNotas::class)->leer($data['foto']->getMimeType(), $data['foto']->get());

                    if ($leido === null || $leido['monto'] === null) {
                        Notification::make()->warning()->title('La IA no está configurada o no pudo leer la nota: regístrala con «Registrar gasto».')->send();

                        return;
                    }

                    $this->hacer(fn () => app(FinanzasService::class)->registrarGasto((int) $data['fondo'], CarbonImmutable::parse($leido['fecha'] ?? Calendario::hoy()), $leido['descripcion'] ?? 'Gasto leído de la foto',
                        $leido['comprobante'], (string) (float) str_replace(',', '.', $leido['monto']), (int) $data['partida'], true), 'Leído por IA: revísalo y apruébalo.');
                }),
            Action::make('rendir')->label('Rendir fondo')->icon('heroicon-o-check-badge')->color('success')->visible(fn (): bool => Fondo::query()->where('estado', 'abierto')->exists())
                ->schema([Select::make('fondo')->label('Fondo')->required()->options(fn (): array => Fondo::query()->where('estado', 'abierto')->pluck('codigo', 'id')->all())])
                ->action(fn (array $data) => $this->hacer(fn () => app(FinanzasService::class)->rendir((int) $data['fondo'], CarbonImmutable::parse(Calendario::hoy())), 'Rendido: el saldo volvió a caja.')),
        ];
    }
}
