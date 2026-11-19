<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Requerimientos\Pages;

use App\Modules\Erp\Compras\Filament\Compras;
use App\Modules\Erp\Compras\Filament\Resources\Ordenes\Pages\VerOrden;
use App\Modules\Erp\Compras\Filament\Resources\Requerimientos\RequerimientoResource;
use App\Modules\Erp\Compras\Models\CuadroComparativo;
use App\Modules\Erp\Compras\Models\Requerimiento;
use App\Modules\Erp\Compras\Models\RequerimientoLinea;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t3-compras): la ficha del requerimiento con su recorrido. */
final class VerRequerimiento extends ViewRecord
{
    protected static string $resource = RequerimientoResource::class;

    private function req(): Requerimiento
    {
        /** @var Requerimiento $r */
        $r = $this->record;

        return $r;
    }

    public function getTitle(): string
    {
        return "{$this->req()->codigo} · Requerimiento";
    }

    public function getSubheading(): string
    {
        $r = $this->req();

        return ComprasService::ESTADOS_REQ[$r->estado].' · '.DB::table('proyectos')->where('id', $r->proyecto_id)->value('codigo').' · '.$r->descripcion.' · '.$r->solicitante?->name;
    }

    private function cuadro(): ?CuadroComparativo
    {
        return CuadroComparativo::query()->where('requerimiento_id', $this->req()->id)->where('estado', 'abierto')->first();
    }

    protected function getHeaderActions(): array
    {
        $s = fn (): ComprasService => app(ComprasService::class);
        $u = fn (): User => auth()->user();
        $en = fn (string $permiso, string ...$estados): \Closure => fn (): bool => in_array($this->req()->estado, $estados, true) && Compras::puede($permiso);

        return [
            Action::make('enviar')->label('Enviar a aprobación')->icon('heroicon-o-paper-airplane')->visible($en(ComprasService::PERMISO_REQUERIR, 'borrador', 'rechazado'))
                ->action(fn () => $this->hacer(fn () => $s()->enviar($this->req()->id), 'Enviado a aprobación.')),
            Action::make('aprobar')->label('Aprobar')->icon('heroicon-o-check')->color('success')->visible($en(ComprasService::PERMISO_APROBAR, 'por_aprobar'))
                ->modalDescription(fn (): string => ($ex = array_filter($s()->vistaPrevia($this->req()), fn (array $p): bool => $p['excede'])) === []
                    ? 'Ninguna partida se excede. Lo que hay en almacén se reserva; lo demás pasa a Compras.'
                    : 'Excede '.implode(', ', array_column($ex, 'codigo')).': registra el motivo. Nadie autoriza un sobrecosto sin saberlo.')
                ->schema([Textarea::make('motivo')->label('Motivo del exceso (si lo hay)')->rows(2)])
                ->action(fn (array $data) => $this->hacer(fn () => $s()->aprobar($this->req()->id, $data['motivo'] ?? null, $u()), 'Aprobado.')),
            Action::make('rechazar')->label('Rechazar')->icon('heroicon-o-x-mark')->color('danger')->visible($en(ComprasService::PERMISO_APROBAR, 'por_aprobar'))
                ->schema([Textarea::make('comentario')->label('Comentario')->required()->rows(2)])
                ->action(fn (array $data) => $this->hacer(fn () => $s()->rechazar($this->req()->id, $data['comentario']), 'Rechazado.')),
            Action::make('despachar')->label('Despachar a obra')->icon('heroicon-o-truck')->color('gray')
                ->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_COMPRAR) && $this->req()->lineas->contains('estado', 'reservada'))
                ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
                ->schema([
                    Select::make('linea')->label('Línea reservada')->required()->options(fn (): array => $this->req()->lineas->where('estado', 'reservada')->mapWithKeys(fn (RequerimientoLinea $l): array => [$l->id => $l->descripcion])->all()),
                    DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                ])
                ->action(fn (array $data) => $this->hacer(fn () => $s()->despachar((int) $data['linea'], CarbonImmutable::parse($data['fecha'])), 'Despachado: el costo pasó a ejecutado.')),
            Action::make('cuadro')->label('Abrir cuadro comparativo')->icon('heroicon-o-table-cells')->color('gray')
                ->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_COMPRAR) && $this->req()->estado === 'aprobado' && $this->req()->lineas->where('atencion', 'compra')->contains('estado', 'pendiente'))
                ->action(fn () => $this->hacer(fn () => $s()->crearCuadro($this->req()->id), 'Cuadro abierto: agrega las cotizaciones de los proveedores.')),
            Action::make('cotizacion')->label('Agregar cotización')->icon('heroicon-o-plus')->color('gray')->slideOver()
                ->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_COMPRAR) && $this->cuadro() !== null)
                ->schema(fn (): array => [
                    Select::make('proveedor')->label('Proveedor')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => DB::table('parties')->where('razon_social', 'ilike', "%{$search}%")->limit(20)->pluck('razon_social', 'id')->all()),
                    Grid::make(3)->schema([
                        TextInput::make('plazo')->label('Plazo (días)')->numeric()->integer()->required(),
                        TextInput::make('forma_pago')->label('Forma de pago')->required()->default('30 días'),
                        TextInput::make('evaluacion')->label('Evaluación (0 a 5)')->numeric(),
                    ]),
                    ...$this->req()->lineas->where('estado', 'en_cuadro')->map(fn (RequerimientoLinea $l) => TextInput::make("precio_{$l->id}")->label("Precio unitario · {$l->descripcion}")->numeric()->required())->values()->all(),
                ])
                ->action(function (array $data) use ($s): void {
                    $precios = [];

                    foreach ($data as $clave => $valor) {
                        if (str_starts_with($clave, 'precio_')) {
                            $precios[(int) substr($clave, 7)] = (string) $valor;
                        }
                    }

                    $this->hacer(fn () => $s()->agregarCotizacion($this->cuadro()->id, (int) $data['proveedor'], (int) $data['plazo'], $data['forma_pago'], filled($data['evaluacion'] ?? null) ? (string) $data['evaluacion'] : null, $precios), 'Cotización agregada.');
                }),
            Action::make('adjudicar')->label('Adjudicar')->icon('heroicon-o-trophy')->color('success')
                ->visible(fn (): bool => Compras::puede(ComprasService::PERMISO_COMPRAR) && ($this->cuadro()?->cotizaciones()->exists() ?? false))
                ->schema([
                    Select::make('cotizacion')->label('Proveedor elegido')->required()
                        ->options(fn (): array => $this->cuadro()?->cotizaciones->mapWithKeys(fn ($c): array => [$c->id => DB::table('parties')->where('id', $c->proveedor_party_id)->value('razon_social')])->all() ?? []),
                    Textarea::make('motivo')->label('Motivo · obligatorio')->required()->rows(3)->placeholder('Menor precio, crédito a 30 días y buen historial.'),
                    TextInput::make('lugar')->label('Lugar de entrega')->default('Obra'),
                ])
                ->action(function (array $data, Action $action) use ($s, $u): void {
                    try {
                        $orden = $s()->adjudicar($this->cuadro()->id, (int) $data['cotizacion'], $data['motivo'], $data['lugar'] ?? null, null, $u());
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title("Adjudicado: nació la orden {$orden->codigo}.")->send();
                    $this->redirect(VerOrden::getUrl(['record' => $orden]));
                }),
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
