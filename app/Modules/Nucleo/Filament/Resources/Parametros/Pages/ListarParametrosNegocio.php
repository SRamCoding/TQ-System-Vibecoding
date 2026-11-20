<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parametros\Pages;

use App\Modules\Nucleo\Filament\Resources\Parametros\ParametroNegocioResource;
use Filament\Resources\Pages\ListRecords;

final class ListarParametrosNegocio extends ListRecords
{
    protected static string $resource = ParametroNegocioResource::class;

    protected static ?string $title = 'Parámetros del negocio';
}
