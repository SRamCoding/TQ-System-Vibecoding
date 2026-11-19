<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

/** MVP rápido (t2-contratos): las adendas, que cambian el monto y el plazo vigentes (y con ellos la penalidad). */
final class AdendasRelationManager extends RelationManager
{
    protected static string $relationship = 'adendas';

    protected static ?string $title = 'Adendas';

    public function table(Table $table): Table
    {
        return $table->paginated(false)
            ->columns([
                TextColumn::make('numero')->label('Adenda'),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('motivo')->label('Motivo')->wrap(),
                TextColumn::make('monto_adicional')->label('Monto adicional (con IGV)')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => ContratoResource::soles($state)),
                TextColumn::make('dias_adicionales')->label('Días más')->alignEnd(),
            ])
            ->headerActions([
                Action::make('registrar')->label('Registrar adenda')->icon('heroicon-o-plus')->visible(fn (): bool => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR))
                    ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'numero' => 'Adenda N.° '.($this->getOwnerRecord()->adendas()->count() + 1), 'monto' => '0', 'dias' => 0])
                    ->schema([
                        TextInput::make('numero')->label('Número')->required()->maxLength(40),
                        DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                        TextInput::make('motivo')->label('Motivo')->required()->maxLength(300),
                        TextInput::make('monto')->label('Monto adicional con IGV (negativo si es deductivo)')->numeric()->required(),
                        TextInput::make('dias')->label('Días adicionales')->numeric()->integer()->required(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(ContratosService::class)->registrarAdenda($this->getOwnerRecord()->getKey(), $data['numero'], CarbonImmutable::parse($data['fecha']), $data['motivo'], (string) $data['monto'], (int) $data['dias']);
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Adenda registrada: cambian el monto, el plazo y la penalidad diaria.')->send();
                    }),
            ]);
    }
}
