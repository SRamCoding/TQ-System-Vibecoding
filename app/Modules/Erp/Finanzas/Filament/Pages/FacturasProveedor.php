<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use BackedEnum;

/** MV-021: las facturas de proveedor, con su cruce de tres vías (orden, recepción y factura), en Finanzas. */
final class FacturasProveedor extends Comprobantes
{
    protected const string CLASE = 'compra';

    protected static ?string $slug = 'facturas-proveedor';

    protected static ?string $title = 'Facturas de proveedor';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    public function getSubheading(): string
    {
        return 'Entran por su XML (validado con SUNAT) y se cruzan con la orden y su recepción; después pasan al tablero de pagos.';
    }

    protected function getHeaderActions(): array
    {
        return $this->accionesDeProveedor();
    }
}
