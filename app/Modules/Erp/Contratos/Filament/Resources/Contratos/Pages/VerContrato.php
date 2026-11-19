<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/** MVP rápido (t2-contratos): la ficha del contrato con Activar, Aplicar penalidad, Culminar y Abrir proyecto. */
final class VerContrato extends ViewRecord
{
    protected static string $resource = ContratoResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Resumen';
    }

    private function contrato(): Contrato
    {
        /** @var Contrato $c */
        $c = $this->record;

        return $c;
    }

    public function getTitle(): string
    {
        return "{$this->contrato()->codigo} · ".mb_substr($this->contrato()->objeto, 0, 70);
    }

    public function getSubheading(): string
    {
        return Contrato::ESTADOS[$this->contrato()->estado].' · '.$this->contrato()->cliente?->razon_social;
    }

    protected function getHeaderActions(): array
    {
        $gestiona = fn (string ...$estados): \Closure => fn (): bool => in_array($this->contrato()->estado, $estados, true) && ContratoResource::puede(ContratosService::PERMISO_GESTIONAR);

        return [
            Action::make('activar')->label('Completar y activar')->icon('heroicon-o-check-badge')->color('success')->visible($gestiona('borrador'))->slideOver()
                ->modalDescription('Lo que la cotización no trae: número de contrato de la entidad, firma, forma de pago y la penalidad de las bases.')
                ->fillForm(fn (): array => ['firma' => Calendario::hoy(), 'forma_pago' => $this->contrato()->forma_pago ?? '100 % a la conformidad', 'garantia' => $this->contrato()->garantia_meses ?? 24,
                    'tope' => '10', 'factor' => ContratosService::factor($this->contrato()->plazo_dias)])
                ->schema([
                    TextInput::make('numero')->label('N.° de contrato de la entidad')->required()->maxLength(80)->placeholder('Contrato N.° 045-2026-MPVN'),
                    DatePicker::make('firma')->label('Fecha de firma')->required()->format('Y-m-d'),
                    TextInput::make('forma_pago')->label('Forma de pago')->maxLength(200),
                    TextInput::make('garantia')->label('Garantía técnica (meses)')->numeric()->integer(),
                    Select::make('factor')->label('Factor F de la penalidad')->options(['0.40' => '0.40 (plazo hasta 60 días)', '0.25' => '0.25 (plazo mayor)'])->required(),
                    TextInput::make('tope')->label('Tope de penalidad (%)')->numeric()->required(),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(ContratosService::class)->activar($this->contrato()->id, $data['numero'], CarbonImmutable::parse($data['firma']),
                    $data['forma_pago'] ?? null, filled($data['garantia'] ?? null) ? (int) $data['garantia'] : null, (string) $data['tope'], $data['factor']), 'Contrato vigente: hitos programados.')),
            Action::make('penalidad')->label('Aplicar penalidad')->icon('heroicon-o-exclamation-triangle')->color('danger')->visible($gestiona('vigente', 'culminado'))
                ->modalDescription(fn (): string => 'Penalidad diaria '.ContratoResource::soles(app(ContratosService::class)->penalidadDiaria($this->contrato())).'; nunca pasa el tope de '.ContratoResource::soles(app(ContratosService::class)->tope($this->contrato())).'.')
                ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
                ->schema([
                    TextInput::make('dias')->label('Días de atraso')->numeric()->integer()->minValue(1)->required(),
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                    TextInput::make('motivo')->label('Motivo')->required()->maxLength(300),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => app(ContratosService::class)->aplicarPenalidad($this->contrato()->id, (int) $data['dias'], CarbonImmutable::parse($data['fecha']), $data['motivo']), 'Penalidad aplicada: baja lo vendido del proyecto.')),
            Action::make('culminar')->label('Culminar')->icon('heroicon-o-flag')->color('gray')->visible($gestiona('vigente'))->requiresConfirmation()
                ->action(fn () => $this->hacer(fn () => app(ContratosService::class)->culminar($this->contrato()->id), 'Contrato culminado.')),
            Action::make('proyecto')->label('Abrir proyecto')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn (): string => VerProyecto::getUrl(['record' => $this->contrato()->proyecto_id])),
        ];
    }

    private function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($exito)->send();
        $this->redirect(self::getUrl(['record' => $this->record]));
    }
}
