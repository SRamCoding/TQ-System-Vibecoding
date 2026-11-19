<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use Filament\Resources\Pages\ListRecords;

/** MVP rápido (t2-contratos): los contratos nacen al ganar una oportunidad; aquí no se crean a mano. */
final class ListarContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    public function getSubheading(): string
    {
        return 'Nacen en borrador al ganar una oportunidad; Administración de Contratos los completa y los activa.';
    }
}
