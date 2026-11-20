<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

/** «Listas» (MV-016): los catálogos y los motivos, que salieron de Configuración hacia Directorio y catálogos. */
final class Listas extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Listas';

    protected static ?string $clusterBreadcrumb = 'Listas';

    protected static ?string $slug = 'listas';
}
