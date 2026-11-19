<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

/**
 * Usuarios y organigrama (plan 1.4, D10), con los roles y sus permisos, los conflictos y los permisos incompatibles
 * (plan 1.4c). Lleva su propia barra de pestañas, así que también se usa con la barra de módulos apagada.
 */
final class Seguridad extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Usuarios y organigrama';

    protected static ?string $clusterBreadcrumb = 'Usuarios y organigrama';

    protected static ?string $slug = 'seguridad';

    protected static ?int $navigationSort = 80;
}
