<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cuentas\Pestanas\ComponentePestanaCuenta;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/** Pestaña «Cotizaciones» de la cuenta (D19): las de todas sus oportunidades, con la oportunidad de cada una. */
final class CotizacionesDeCuenta extends ComponentePestanaCuenta implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use TablaDeCotizaciones;

    public function table(Table $table): Table
    {
        return $this->tablaDeCotizaciones($table, fn (Builder $query): Builder => $query->where('cuenta_id', $this->cuentaId), true)
            ->emptyStateDescription('Una cotización nace de una oportunidad: créala desde su ficha.');
    }

    public function render(): View
    {
        return view('cotizaciones::tabla');
    }
}
