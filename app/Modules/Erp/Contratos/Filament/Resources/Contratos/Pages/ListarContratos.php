<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\AccionesContrato;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Contratos\Services\TableroContratos;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Observación 6.11 (ficha t2-contratos): `/contratos` en tarjetas que avisan antes, con cinco cifras arriba, filtros en
 * chips, orden por urgencia y una segunda vista, «Calendario de vencimientos». Un clic abre el resumen en un panel
 * deslizante con sus botones; doble clic, la ficha. Los contratos nacen al ganar una oportunidad: aquí no se crean.
 */
final class ListarContratos extends Page
{
    protected static string $resource = ContratoResource::class;

    protected string $view = 'contratos::listado';

    protected static ?string $title = 'Contratos';

    #[Url]
    public string $vista = 'contratos';

    #[Url]
    public ?string $filtro = null;

    #[Url]
    public string $buscar = '';

    #[Url]
    public ?int $cliente = null;

    #[Url]
    public string $orden = 'urgencia';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function poner(string $campo, mixed $valor): void
    {
        match ($campo) {
            'vista' => $this->vista = in_array($valor, ['contratos', 'calendario'], true) ? $valor : 'contratos',
            'filtro' => $this->filtro = $this->filtro === $valor || ! isset(TableroContratos::FILTROS[(string) $valor]) ? null : (string) $valor,
            'cliente' => $this->cliente = filled($valor) ? (int) $valor : null,
            'orden' => $this->orden = isset(TableroContratos::ORDENES[(string) $valor]) ? (string) $valor : 'urgencia',
            default => null,
        };
    }

    public function limpiar(): void
    {
        $this->filtro = null;
        $this->buscar = '';
        $this->cliente = null;
    }

    /**
     * Lo que dibuja la vista: cifras (de todos), conteos por chip, clientes y las tarjetas filtradas y ordenadas.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $tablero = app(TableroContratos::class);
        $todas = $tablero->tarjetas();
        $buscar = mb_strtolower(trim($this->buscar));
        $base = array_values(array_filter($todas, fn (array $t): bool => ($this->cliente === null || $t['cliente_id'] === $this->cliente)
            && ($buscar === '' || str_contains(mb_strtolower($t['codigo'].' '.$t['numero_entidad'].' '.$t['objeto'].' '.$t['cliente']), $buscar))));
        $tarjetas = $tablero->ordenar(array_values(array_filter($base, fn (array $t): bool => $this->filtro === null || $tablero->cumple($t, $this->filtro))), $this->orden);
        $clientes = collect($todas)->pluck('cliente', 'cliente_id')->sort()->all();

        return [
            'cifras' => $tablero->cifras($todas),
            'cuentas' => collect(TableroContratos::FILTROS)->map(fn (string $n, string $clave): int => count(array_filter($base, fn (array $t): bool => $tablero->cumple($t, $clave))))->all(),
            'clientes' => $clientes,
            'tarjetas' => $tarjetas,
            'agrupar' => $this->orden === 'urgencia',
            'calendario' => $this->vista === 'calendario' ? $tablero->calendario() : [],
            'gestiona' => ContratoResource::puede(ContratosService::PERMISO_GESTIONAR),
            'hayFiltros' => $this->filtro !== null || $buscar !== '' || $this->cliente !== null,
        ];
    }

    public function activarAction(): Action
    {
        return AccionesContrato::activar(AccionesContrato::delArgumento());
    }

    /** El resumen rápido en un panel deslizante, con sus botones y «Abrir ficha». */
    public function resumenAction(): Action
    {
        return Action::make('resumen')->slideOver()->modalWidth('lg')
            ->visible(fn (): bool => ContratoResource::canViewAny())
            ->modalHeading(fn (array $arguments): string => (string) Contrato::query()->whereKey((int) ($arguments['contrato'] ?? 0))->value('codigo'))
            ->modalDescription(fn (array $arguments): string => (string) Contrato::query()->whereKey((int) ($arguments['contrato'] ?? 0))->value('objeto'))
            ->modalContent(fn (array $arguments) => view('contratos::resumen', ['t' => app(TableroContratos::class)->tarjeta(AccionesContrato::delArgumento()($arguments))]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(function (array $arguments): array {
                $id = (int) ($arguments['contrato'] ?? 0);
                $contrato = fn (): Contrato => Contrato::query()->findOrFail($id);
                $estado = (string) Contrato::query()->whereKey($id)->value('estado');

                return array_values(array_filter([
                    $estado === 'borrador' ? AccionesContrato::activar($contrato) : null,
                    in_array($estado, ['vigente', 'culminado'], true) ? AccionesContrato::penalidad($contrato) : null,
                    $estado === 'vigente' ? AccionesContrato::adenda($contrato)->color('gray') : null,
                    in_array($estado, ['borrador', 'vigente'], true) ? AccionesContrato::garantia($contrato)->color('gray') : null,
                    Action::make('abrirFicha')->label('Abrir ficha')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(VerContrato::getUrl(['record' => $id])),
                ]));
            });
    }
}
