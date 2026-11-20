<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Ordenes\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Filament\Resources\Ordenes\OrdenResource;
use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Compras\Models\OrdenLinea;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/** MVP rápido (t3-compras): la ficha de la orden: aprobar (con motivo si excede), registrar recepción, seguimiento y pagar. */
final class VerOrden extends ViewRecord
{
    protected static string $resource = OrdenResource::class;

    private function orden(): Orden
    {
        /** @var Orden $o */
        $o = $this->record;

        return $o;
    }

    public function getTitle(): string
    {
        return "{$this->orden()->codigo} · {$this->orden()->proveedor_nombre}";
    }

    public function getSubheading(): string
    {
        return ComprasService::ESTADOS_ORDEN[$this->orden()->estado].' · '.$this->orden()->tipo->nombre.' · '.Compras::soles(OrdenResource::total($this->orden()));
    }

    protected function getHeaderActions(): array
    {
        $s = fn (): ComprasService => app(ComprasService::class);
        $en = fn (string $permiso, string ...$estados): \Closure => fn (): bool => in_array($this->orden()->estado, $estados, true) && Compras::puede($permiso);
        $conformidad = fn (): bool => $this->orden()->tipo->atencion === 'conformidad';

        return [
            Action::make('aprobar')->label('Aprobar')->icon('heroicon-o-check')->color('success')->visible($en(ComprasService::PERMISO_APROBAR, 'borrador'))
                ->modalDescription(fn (): string => ($ex = $s()->excesos($this->orden())) === []
                    ? 'Al aprobarse, su monto pasa a comprometido en cada partida.'
                    : implode(', ', array_map(fn (string $c, string $m): string => "Excede {$c} en S/ ".number_format((float) $m, 2), array_keys($ex), $ex)).': la aprobación exige motivo.')
                ->schema([Textarea::make('motivo')->label('Motivo del exceso (si lo hay)')->rows(2)])
                ->action(fn (array $data) => $this->hacer(fn () => $s()->aprobarOrden($this->orden()->id, $data['motivo'] ?? null, auth()->user()), 'Aprobada: el monto quedó comprometido.')),
            Action::make('recibir')->label(fn (): string => $conformidad() ? 'Dar conformidad' : 'Registrar recepción')->icon('heroicon-o-inbox-arrow-down')->color('warning')
                ->visible($en(ComprasService::PERMISO_COMPRAR, 'aprobada', 'recibida_parcial'))->slideOver()
                ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'lugar' => 'obra'] + $this->orden()->lineas->mapWithKeys(fn (OrdenLinea $l): array => ["linea_{$l->id}" => bcsub((string) $l->cantidad, (string) $l->cantidad_recibida, 3)])->all())
                ->schema(fn (): array => [
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                    TextInput::make('guia')->label('Guía de remisión')->maxLength(60),
                    Select::make('lugar')->label('Dónde')->options(['obra' => 'En obra', 'almacen' => 'En el Almacén Central'])->required(),
                    ...$this->orden()->lineas->map(fn (OrdenLinea $l) => TextInput::make("linea_{$l->id}")->label("Recibido · {$l->descripcion} (pedido ".rtrim(rtrim((string) $l->cantidad, '0'), '.').', ya recibido '.rtrim(rtrim((string) $l->cantidad_recibida, '0'), '.').')')->numeric()->minValue(0))->values()->all(),
                    Textarea::make('observacion')->label('Observación')->rows(2),
                ])
                ->action(function (array $data) use ($s): void {
                    $cantidades = [];

                    foreach ($data as $k => $v) {
                        if (str_starts_with($k, 'linea_') && filled($v)) {
                            $cantidades[(int) substr($k, 6)] = (string) $v;
                        }
                    }

                    $this->hacer(fn () => $s()->recibir($this->orden()->id, $cantidades, CarbonImmutable::parse($data['fecha']), $data['guia'] ?? null, $data['lugar'], $data['observacion'] ?? null), 'Recepción registrada: el costo pasó a ejecutado.');
                }),
            Action::make('seguimiento')->label('Seguimiento')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('gray')->visible($en(ComprasService::PERMISO_COMPRAR, 'aprobada', 'recibida_parcial'))
                ->fillForm(fn (): array => ['seguimiento' => $this->orden()->seguimiento])
                ->schema([Textarea::make('seguimiento')->label('Seguimiento')->rows(2)->placeholder('Faltan 130 m · el proveedor confirmó para el 19/09')])
                ->action(fn (array $data) => $this->hacer(fn () => $this->orden()->update(['seguimiento' => $data['seguimiento']]), 'Anotado.')),
            Action::make('pagar')->label('Marcar pagada')->icon('heroicon-o-banknotes')->color('gray')->visible($en(ComprasService::PERMISO_COMPRAR, 'recibida', 'recibida_parcial'))
                ->requiresConfirmation()
                ->action(fn () => $this->hacer(fn () => $s()->pagar($this->orden()->id), 'Pagada.')),
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
