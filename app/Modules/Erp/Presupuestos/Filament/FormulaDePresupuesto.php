<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\FormulaPolinomicaService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * MVP rápido (t2-formula-polinomica): la pestaña «Fórmula polinómica» del presupuesto: los monomios con su índice y su
 * coeficiente, la suma (que tiene que dar 1.000) y el factor de reajuste simulado entre dos meses de índices.
 */
final class FormulaDePresupuesto extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $presupuestoId;

    public ?string $base = null;

    public ?string $mes = null;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
        $periodos = array_keys(app(FormulaPolinomicaService::class)->periodos());
        $this->mes = $periodos[0] ?? null;
        $this->base = $periodos[count($periodos) - 1] ?? null;
    }

    private function servicio(): FormulaPolinomicaService
    {
        return app(FormulaPolinomicaService::class);
    }

    public function table(Table $table): Table
    {
        $suma = $this->servicio()->suma($this->presupuestoId);
        $completa = bccomp($suma, '1', 3) === 0;

        try {
            $factor = $completa && $this->base !== null && $this->mes !== null ? $this->servicio()->factor($this->presupuestoId, $this->base, $this->mes) : null;
        } catch (RuntimeException) {
            $factor = null;
        }

        $terminos = $factor === null ? [] : array_column($factor['filas'], 'termino', 'descripcion');

        return $table
            ->records(fn (): array => array_map(fn (object $m): array => [
                '__key' => (string) $m->id, 'id' => $m->id, 'descripcion' => $m->descripcion, 'indice' => $m->indice_codigo, 'coeficiente' => (string) $m->coeficiente,
                'termino' => $terminos[$m->descripcion] ?? null,
            ], $this->servicio()->monomios($this->presupuestoId)))
            ->paginated(false)
            ->heading('Fórmula polinómica de reajuste')
            ->description(($completa ? "Suma {$suma} ✓" : "Suma {$suma}: tiene que dar 1.000")
                .($factor === null ? '' : " · Factor de reajuste {$this->mes} sobre {$this->base}: K = {$factor['k']} (Σ coef × índice del mes ÷ índice base)"))
            ->columns([
                TextColumn::make('descripcion')->label('Monomio'),
                TextColumn::make('indice')->label('Índice unificado')->formatStateUsing(fn (string $state): string => $this->servicio()->indices()[$state] ?? $state),
                TextColumn::make('coeficiente')->label('Coef.')->alignEnd()->fontFamily('mono'),
                TextColumn::make('termino')->label('Coef. × índice mes ÷ base')->alignEnd()->fontFamily('mono')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('simular')->label('Cambiar meses')->icon('heroicon-o-calculator')->color('gray')
                    ->fillForm(fn (): array => ['base' => $this->base, 'mes' => $this->mes])
                    ->schema([
                        Select::make('base')->label('Mes base (de la oferta)')->options(fn (): array => $this->servicio()->periodos())->required(),
                        Select::make('mes')->label('Mes de la valorización')->options(fn (): array => $this->servicio()->periodos())->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->base = $data['base'];
                        $this->mes = $data['mes'];
                        $this->resetTable();
                    }),
                Action::make('agregar')->label('Agregar monomio')->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('descripcion')->label('Monomio')->required()->maxLength(120)->placeholder('Mano de obra'),
                        Select::make('indice')->label('Índice unificado')->options(fn (): array => $this->servicio()->indices())->required()->searchable(),
                        TextInput::make('coeficiente')->label('Coeficiente')->numeric()->required()->minValue(0.001)->maxValue(1)->step(0.001),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $this->servicio()->agregar($this->presupuestoId, $data['descripcion'], $data['indice'], number_format((float) $data['coeficiente'], 3, '.', ''));
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }

                        $this->resetTable();
                    }),
            ])
            ->recordActions([
                Action::make('quitar')->label('Quitar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->action(function (array $record): void {
                        try {
                            $this->servicio()->quitar((int) $record['id']);
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }

                        $this->resetTable();
                    }),
            ])
            ->emptyStateHeading('Sin fórmula polinómica')
            ->emptyStateDescription('Se define con el presupuesto y se aplica en cada valorización con los índices del mes.');
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }
}
