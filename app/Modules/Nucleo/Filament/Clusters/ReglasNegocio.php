<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

/** «Reglas del negocio» (MV-016): las reglas de aprobación y los parámetros del negocio, que salieron de Configuración hacia Techquk. */
final class ReglasNegocio extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Reglas del negocio';

    protected static ?string $clusterBreadcrumb = 'Reglas del negocio';

    protected static ?string $slug = 'reglas-negocio';
}
