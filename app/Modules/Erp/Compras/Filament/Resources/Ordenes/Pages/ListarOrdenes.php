<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Filament\Resources\Ordenes\Pages;

use App\Modules\Erp\Compras\Filament\Resources\Ordenes\OrdenResource;
use Filament\Resources\Pages\ListRecords;

/** MVP rápido (t3-compras): las órdenes nacen al adjudicar un cuadro (o directas, como la comisión de una fianza). */
final class ListarOrdenes extends ListRecords
{
    protected static string $resource = OrdenResource::class;

    public function getTitle(): string
    {
        return 'Órdenes';
    }
}
