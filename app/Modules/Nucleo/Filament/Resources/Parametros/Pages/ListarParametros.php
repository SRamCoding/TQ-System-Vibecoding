<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parametros\Pages;

use App\Modules\Nucleo\Filament\Resources\Parametros\ParametroResource;
use Filament\Resources\Pages\ListRecords;

final class ListarParametros extends ListRecords
{
    protected static string $resource = ParametroResource::class;

    protected static ?string $title = 'Parámetros';
}
