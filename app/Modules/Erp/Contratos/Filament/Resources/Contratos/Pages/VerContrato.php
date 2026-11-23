<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\AccionesContrato;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
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

        $recargar = fn () => $this->redirect(self::getUrl(['record' => $this->record]));

        return [
            AccionesContrato::activar(fn (): Contrato => $this->contrato(), $recargar)->visible($gestiona('borrador')),
            AccionesContrato::penalidad(fn (): Contrato => $this->contrato(), $recargar)->visible($gestiona('vigente', 'culminado')),
            Action::make('culminar')->label('Culminar')->icon('heroicon-o-flag')->color('gray')->visible($gestiona('vigente'))->requiresConfirmation()
                ->action(fn () => $this->hacer(fn () => app(ContratosService::class)->culminar($this->contrato()->id), 'Contrato culminado.')),
            Action::make('liquidar')->label('Liquidar')->icon('heroicon-o-calculator')->color('primary')->visible($gestiona('vigente', 'culminado'))
                ->modalDescription(function (): string {
                    $l = app(ContratosService::class)->liquidacion($this->contrato());

                    return 'Monto final '.ContratoResource::soles($l['monto_final']).' (vendido neto más reajustes '.ContratoResource::soles($l['reajustes']).') − facturado '.ContratoResource::soles($l['facturado'])
                        .' = saldo '.(bccomp($l['saldo'], '0', 2) >= 0 ? 'a favor de Techquk ' : 'a favor de la entidad ').ContratoResource::soles(ltrim($l['saldo'], '-')).'.'.($l['bloqueo'] === null ? '' : ' '.$l['bloqueo']);
                })
                ->requiresConfirmation()
                ->action(fn () => $this->hacer(fn () => app(ContratosService::class)->liquidar($this->contrato()->id, CarbonImmutable::parse(Calendario::hoy())), 'Contrato liquidado.')),
            Action::make('consorcio')->label('Consorcio')->icon('heroicon-o-users')->color('gray')->visible($gestiona('borrador', 'vigente'))
                ->fillForm(fn (): array => ['consorcio' => $this->contrato()->consorcio, 'participacion' => $this->contrato()->participacion_pct, 'modo' => $this->contrato()->modo_consorcio ?? 'participacion'])
                ->schema([
                    TextInput::make('consorcio')->label('Nombre del consorcio (vacío: sin consorcio)')->maxLength(150),
                    TextInput::make('participacion')->label('Participación de Techquk (%)')->numeric()->required(),
                    Select::make('modo')->label('Modo de registro')->options(['participacion' => 'Solo la participación de Techquk', 'todo' => 'Todo el contrato con porcentajes (Techquk es el operador)'])->required(),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => $this->contrato()->update(['consorcio' => $data['consorcio'] ?: null, 'participacion_pct' => $data['consorcio'] ? $data['participacion'] : 100,
                    'modo_consorcio' => $data['consorcio'] ? $data['modo'] : null]), 'Guardado: lo vendido se calcula con la participación.')),
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
