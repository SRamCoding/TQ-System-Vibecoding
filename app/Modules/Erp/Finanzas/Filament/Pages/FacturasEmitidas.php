<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use BackedEnum;
use Illuminate\Support\Facades\DB;

/** MV-021: las facturas que emite Techquk, en Facturación y cobranza. Se emiten desde una valorización aprobada. */
final class FacturasEmitidas extends Comprobantes
{
    protected const string CLASE = 'venta';

    protected static ?string $slug = 'facturas-emitidas';

    protected static ?string $title = 'Facturas emitidas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public function getSubheading(): string
    {
        $porFacturar = DB::table('valorizaciones')->where('estado', 'aprobada')->count();

        return $porFacturar > 0 ? "Por facturar: {$porFacturar} valorizaciones aprobadas." : 'No hay valorizaciones aprobadas por facturar.';
    }

    protected function getHeaderActions(): array
    {
        return [$this->accionFacturar()];
    }
}
