<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\GarantiaContrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t2-contratos): las garantías con sus dos caras: el monto garantizado y la comisión imputada al proyecto. */
final class GarantiasRelationManager extends RelationManager
{
    protected static string $relationship = 'garantias';

    protected static ?string $title = 'Garantías';

    public function table(Table $table): Table
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return $table->paginated(false)
            ->description('Cara 1, la garantía: monto, vencimiento, renovaciones y devolución; no es gasto. Cara 2, el costo: la comisión del banco va a los gastos generales del proyecto. Avisa a 60, 30 y 15 días del vencimiento.')
            ->columns([
                TextColumn::make('tipo')->label('Garantía')->formatStateUsing(fn (string $state): string => ContratosService::TIPOS_GARANTIA[$state])->description(fn (GarantiaContrato $record): ?string => $record->numero),
                TextColumn::make('banco')->label('Banco'),
                TextColumn::make('monto')->label('Monto garantizado')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => ContratoResource::soles($state)),
                TextColumn::make('vencimiento')->label('Vence')->date('d/m/Y')
                    ->description(function (GarantiaContrato $record) use ($hoy): ?string {
                        $d = (int) $hoy->diffInDays($record->vencimiento, false);

                        return in_array($record->estado, ['vigente', 'renovada'], true) && $d <= 60 ? ($d < 0 ? 'vencida' : "en {$d} días") : null;
                    })
                    ->color(fn (GarantiaContrato $record): ?string => in_array($record->estado, ['vigente', 'renovada'], true) && (int) $hoy->diffInDays($record->vencimiento, false) <= 30 ? 'danger' : null),
                TextColumn::make('comision')->label('Costo (comisión)')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => ContratoResource::soles($state))
                    ->description(fn (GarantiaContrato $record): ?string => $record->gasto_id === null ? null : DB::table('gastos_demo')->where('id', $record->gasto_id)->value('numero').' › gastos generales'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ContratosService::ESTADOS_GARANTIA[$state])
                    ->color(fn (string $state): string => match ($state) {
                        'vigente', 'renovada' => 'success', 'solicitada' => 'warning', default => 'gray'
                    }),
            ])
            ->headerActions([
                Action::make('registrar')->label('Registrar garantía')->icon('heroicon-o-plus')->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
                    ->fillForm(fn (): array => ['tipo' => 'fiel_cumplimiento', 'monto' => app(ContratosService::class)->tope($this->getOwnerRecord()), 'emision' => Calendario::hoy(),
                        'vencimiento' => app(ContratosService::class)->fin($this->getOwnerRecord())->addDays(30)->toDateString(), 'estado' => 'vigente'])
                    ->schema([
                        Select::make('tipo')->label('Tipo')->options(ContratosService::TIPOS_GARANTIA)->required(),
                        TextInput::make('banco')->label('Banco')->required()->maxLength(120),
                        TextInput::make('numero')->label('N.° de carta fianza')->maxLength(60),
                        TextInput::make('monto')->label('Monto garantizado')->numeric()->required(),
                        DatePicker::make('emision')->label('Emisión')->required()->format('Y-m-d'),
                        DatePicker::make('vencimiento')->label('Vencimiento')->required()->format('Y-m-d'),
                        TextInput::make('comision')->label('Comisión del banco')->numeric()->helperText('Se imputa a los gastos generales del proyecto.'),
                        Select::make('estado')->label('Estado')->options(ContratosService::ESTADOS_GARANTIA)->required(),
                    ])
                    ->action(function (array $data): void {
                        app(ContratosService::class)->registrarGarantia($this->getOwnerRecord()->getKey(), $data['tipo'], $data['banco'], $data['numero'] ?? null, (string) $data['monto'],
                            CarbonImmutable::parse($data['emision']), CarbonImmutable::parse($data['vencimiento']), filled($data['comision'] ?? null) ? (string) $data['comision'] : null, $data['estado']);
                        Notification::make()->success()->title('Garantía registrada.')->send();
                    }),
            ])
            ->recordActions([
                Action::make('estado')->label('Cambiar estado')->icon('heroicon-o-arrow-path')->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
                    ->fillForm(fn (GarantiaContrato $record): array => ['estado' => $record->estado, 'vencimiento' => $record->vencimiento->toDateString()])
                    ->schema([
                        Select::make('estado')->label('Estado')->options(ContratosService::ESTADOS_GARANTIA)->required(),
                        DatePicker::make('vencimiento')->label('Vencimiento (si se renovó)')->format('Y-m-d'),
                    ])
                    ->action(fn (GarantiaContrato $record, array $data) => app(ContratosService::class)->cambiarEstadoGarantia($record->id, $data['estado'], filled($data['vencimiento'] ?? null) ? CarbonImmutable::parse($data['vencimiento']) : null)),
            ]);
    }
}
