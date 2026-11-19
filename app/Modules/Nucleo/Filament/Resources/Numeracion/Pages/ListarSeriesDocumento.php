<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Numeracion\Pages;

use App\Modules\Nucleo\Filament\Resources\Numeracion\SerieDocumentoResource;
use Filament\Resources\Pages\ListRecords;

final class ListarSeriesDocumento extends ListRecords
{
    protected static string $resource = SerieDocumentoResource::class;

    protected static ?string $title = 'Numeración';
}
