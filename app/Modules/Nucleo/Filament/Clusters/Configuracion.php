<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

/**
 * Configuración del núcleo: parámetros, tipos de cambio, catálogos, estados de documentos y motivos. Lleva su propia
 * barra de pestañas, así que también se usa con la barra de módulos apagada.
 */
final class Configuracion extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $clusterBreadcrumb = 'Configuración';

    protected static ?string $slug = 'configuracion';

    protected static ?int $navigationSort = 90;
}
