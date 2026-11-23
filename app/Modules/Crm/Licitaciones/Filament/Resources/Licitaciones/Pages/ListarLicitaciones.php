<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use Filament\Resources\Pages\ListRecords;

/**
 * Observación 7.1: la lista de licitaciones ya no es una pestaña; la dirección lleva a Oportunidades con el filtro TIPO
 * «Licitación pública» puesto. «Registrar convocatoria» vive en `LicitacionResource::accionRegistrar()`.
 */
final class ListarLicitaciones extends ListRecords
{
    protected static string $resource = LicitacionResource::class;

    public function mount(): void
    {
        $this->redirect(TarjetasOportunidades::getUrl(['tipo' => ['licitacion_publica']]), navigate: true);
    }
}
