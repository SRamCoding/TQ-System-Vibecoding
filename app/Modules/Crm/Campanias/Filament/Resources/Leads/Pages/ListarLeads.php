<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Leads\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use Filament\Resources\Pages\ListRecords;

/**
 * Observación 7.1: «Por calificar» ya no es una pestaña; la dirección lleva a Oportunidades con la etapa «Por calificar»
 * puesta. «Nuevo lead» vive en `LeadResource::accionNuevo()`.
 */
final class ListarLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    public function mount(): void
    {
        $this->redirect(TarjetasOportunidades::getUrl(['etapa' => ['por_calificar']]), navigate: true);
    }
}
