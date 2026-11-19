<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Conflictos\Pages;

use App\Modules\Nucleo\Filament\Resources\Conflictos\ConflictoResource;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Abre con los conflictos abiertos. «Pendiente» es lo que dice `crucesPendientes()`, la misma lista de la alerta diaria:
 * un control vencido cuenta como pendiente. «Sin efecto hoy» es un cruce abierto sin control vigente de quien hoy no junta
 * los dos permisos: su rol acotado aún no empieza, o venció y la revisión diaria todavía no lo cierra.
 */
final class ListarConflictos extends ListRecords
{
    public const PENDIENTE = 'Pendiente';

    public const CONTROLADO = 'Controlado';

    public const SIN_EFECTO = 'Sin efecto hoy';

    public const CERRADO = 'Cerrado';

    protected static string $resource = ConflictoResource::class;

    protected static ?string $title = 'Conflictos';

    /** @var ?list<int> leídos una vez por pedido: privado, Livewire no lo guarda */
    private ?array $pendientes = null;

    public function getTabs(): array
    {
        $controlados = fn (Builder $query): Builder => $query->whereNull('hasta')->scopes('conControlVigente');

        return [
            'abiertos' => Tab::make('Abiertos')->badge(CruceFunciones::query()->whereNull('hasta')->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('hasta')),
            'pendientes' => Tab::make('Pendientes')->badge(count($this->pendientes()))->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereKey($this->pendientes())),
            'controlados' => Tab::make('Controlados')->badge($controlados(CruceFunciones::query())->count())
                ->modifyQueryUsing($controlados),
            'cerrados' => Tab::make('Cerrados')->badge(CruceFunciones::query()->whereNotNull('hasta')->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('hasta')),
        ];
    }

    public function estado(CruceFunciones $cruce): string
    {
        return match (true) {
            $cruce->hasta !== null => self::CERRADO,
            in_array($cruce->id, $this->pendientes(), true) => self::PENDIENTE,
            app(SeguridadService::class)->estaControlado($cruce) => self::CONTROLADO,
            default => self::SIN_EFECTO,
        };
    }

    /**
     * Después de una acción. Las pestañas se leen antes de que corra (Filament las usa para hallar la fila) y Filament las
     * guarda: sin esto, la fila y sus cantidades saldrían con lo de antes hasta el siguiente pedido.
     */
    public function releer(): void
    {
        $this->pendientes = null;
        $this->cachedTabs = $this->getTabs();
    }

    /** @return list<int> */
    private function pendientes(): array
    {
        return $this->pendientes ??= app(SeguridadService::class)->crucesPendientes()->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
