<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Filament\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Valorizaciones\Models\Valorizacion;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
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
use Livewire\Attributes\Url;
use RuntimeException;

/** MVP rápido (t4-valorizaciones): las valorizaciones de un contrato, con su cuenta completa y sus adelantos. */
final class ValorizacionesDeContrato extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'valorizaciones';

    protected static ?string $title = 'Valorizaciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    #[Url]
    public ?int $contrato = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(ValorizacionesService::PERMISO_VER);
    }

    public function mount(): void
    {
        $this->contrato ??= (int) Valorizacion::query()->orderByDesc('id')->value('contrato_id') ?: null;
    }

    private function c(): ?Contrato
    {
        return $this->contrato === null ? null : Contrato::query()->find($this->contrato);
    }

    public function getSubheading(): string
    {
        $c = $this->c();

        if ($c === null) {
            return 'Elige un contrato.';
        }

        $s = app(ValorizacionesService::class);
        $dado = (string) (DB::table('contrato_adelantos')->where('contrato_id', $c->id)->where('tipo', 'directo')->sum('monto') ?: '0');

        return "{$c->codigo} · ".mb_substr($c->objeto, 0, 60).' · Adelanto directo '.Compras::soles($dado).': por amortizar '.Compras::soles($s->porAmortizar($c->id, 'directo'))
            .' · Neto = avance + reajuste − amortizaciones − retención − penalidad del periodo. Al aprobarse, habilita su comprobante por el neto.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $m = fn (string $c, string $l, bool $menos = false) => TextColumn::make($c)->label($l)->alignEnd()->fontFamily('mono')
            ->formatStateUsing(fn ($state): string => ($menos && (float) $state > 0 ? '−' : '').number_format((float) $state, 2));

        return $table
            ->query(fn () => Valorizacion::query()->where('contrato_id', $this->contrato ?? 0))
            ->defaultSort('numero', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('numero')->label('Val.')->formatStateUsing(fn ($state, Valorizacion $record): string => "{$state} · {$record->periodo}"),
                $m('avance', 'Avance'),
                TextColumn::make('reajuste')->label('Reajuste')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))->description(fn (Valorizacion $record): string => "K {$record->factor}"),
                $m('amortizacion_directo', 'Amort. directo', true),
                $m('amortizacion_materiales', 'Amort. materiales', true),
                $m('retencion', 'Retención', true),
                $m('penalidad', 'Penalidad', true),
                TextColumn::make('neto')->label('Neto a facturar')->alignEnd()->fontFamily('mono')->weight('bold')->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => $state === 'aprobada' ? 'Aprobada · por facturar' : 'Borrador')
                    ->color(fn (string $state): string => $state === 'aprobada' ? 'success' : 'warning'),
            ])
            ->recordActions([
                Action::make('aprobar')->label('Aprobar y habilitar factura')->button()->color('success')->requiresConfirmation()
                    ->hidden(fn (Valorizacion $record): bool => $record->estado !== 'borrador')
                    ->action(fn (Valorizacion $record) => app(ValorizacionesService::class)->aprobar($record->id)),
            ])
            ->emptyStateHeading('Sin valorizaciones');
    }

    protected function getHeaderActions(): array
    {
        $puede = fn (): bool => auth()->user()?->can(ValorizacionesService::PERMISO_GESTIONAR) ?? false;

        return [
            Action::make('contrato')->label('Contrato')->icon('heroicon-o-document-check')->color('gray')
                ->schema([Select::make('contrato')->label('Contrato')->required()->options(fn (): array => Contrato::query()->whereIn('estado', ['vigente', 'culminado'])->orderBy('codigo')->get()->mapWithKeys(fn (Contrato $c): array => [$c->id => "{$c->codigo} · ".mb_substr($c->objeto, 0, 60)])->all())])
                ->action(function (array $data): void {
                    $this->contrato = (int) $data['contrato'];
                    $this->resetTable();
                }),
            Action::make('adelanto')->label('Registrar adelanto')->icon('heroicon-o-banknotes')->color('gray')->visible(fn (): bool => $puede() && $this->contrato !== null)
                ->fillForm(fn (): array => ['tipo' => 'directo', 'porcentaje' => '10', 'fecha' => Calendario::hoy()])
                ->schema([
                    Select::make('tipo')->label('Tipo')->options(['directo' => 'Adelanto directo', 'materiales' => 'Adelanto de materiales'])->required(),
                    TextInput::make('monto')->label('Monto sin IGV')->numeric()->required(),
                    TextInput::make('porcentaje')->label('Se amortiza (% del avance)')->numeric()->required(),
                    DatePicker::make('fecha')->label('Recibido el')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => app(ValorizacionesService::class)->registrarAdelanto((int) $this->contrato, $data['tipo'], (string) $data['monto'], (string) $data['porcentaje'], $data['fecha'])),
            Action::make('nueva')->label('Nueva valorización')->icon('heroicon-o-plus')->slideOver()->visible(fn (): bool => $puede() && $this->contrato !== null)
                ->modalDescription('Avance del periodo por partida (porcentaje sobre su precio contractual). El reajuste sale de la fórmula polinómica del presupuesto con los índices del mes.')
                ->fillForm(fn (): array => ['periodo' => Calendario::ahora()->format('Y-m'), 'materiales' => '0', 'retencion' => '0'])
                ->schema(fn (): array => [
                    TextInput::make('periodo')->label('Periodo (AAAA-MM)')->required()->regex('/^\d{4}-\d{2}$/'),
                    TextInput::make('materiales')->label('Amortización del adelanto de materiales (según consumo)')->numeric(),
                    TextInput::make('retencion')->label('Retención de garantía (%, si aplica en lugar de fianza)')->numeric(),
                    ...collect(app(ValorizacionesService::class)->partidasContractuales($this->c()))->map(fn (array $p) => TextInput::make('p_'.str_replace('.', '_', $p['codigo']))
                        ->label("{$p['codigo']} {$p['descripcion']} · ".number_format((float) $p['total'], 2).' · ya valorizado '.(app(ValorizacionesService::class)->acumulado($this->contrato)[$p['codigo']] ?? '0').' %')->numeric()->suffix('%'))->all(),
                ])
                ->action(function (array $data): void {
                    $avances = [];

                    foreach ($data as $k => $v) {
                        if (str_starts_with($k, 'p_') && filled($v)) {
                            $avances[str_replace('_', '.', substr($k, 2))] = (string) $v;
                        }
                    }

                    try {
                        app(ValorizacionesService::class)->armar((int) $this->contrato, $data['periodo'], $avances, (string) ($data['materiales'] ?? '0'), (string) ($data['retencion'] ?? '0'));
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
