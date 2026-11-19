<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Estados\Pages;

use App\Modules\Nucleo\Filament\Resources\Estados\EstadoResource;
use Filament\Resources\Pages\ListRecords;

final class ListarEstados extends ListRecords
{
    protected static string $resource = EstadoResource::class;

    protected static ?string $title = 'Estados de documentos';
}
