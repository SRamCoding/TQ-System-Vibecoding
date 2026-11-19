<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Incompatibilidades\Pages;

use App\Modules\Nucleo\Filament\Resources\Incompatibilidades\IncompatibilidadResource;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListarIncompatibilidades extends ListRecords
{
    protected static string $resource = IncompatibilidadResource::class;

    protected static ?string $title = 'Permisos incompatibles';

    public function getTabs(): array
    {
        $pestana = fn (string $nombre, bool $activos): Tab => Tab::make($nombre)
            ->badge(IncompatibilidadPermisos::query()->where('es_activa', $activos)->count())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('es_activa', $activos));

        return ['activos' => $pestana('Activos', true), 'desactivados' => $pestana('Desactivados', false)];
    }

    /** Después de una acción: Filament ya leyó las pestañas para hallar la fila, y sus cantidades serían las de antes. */
    public function releer(): void
    {
        $this->cachedTabs = $this->getTabs();
    }

    protected function getHeaderActions(): array
    {
        return [IncompatibilidadResource::accionAgregar()];
    }
}
