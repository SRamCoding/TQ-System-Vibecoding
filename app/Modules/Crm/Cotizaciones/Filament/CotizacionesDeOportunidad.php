<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Pestanas\ComponentePestanaOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Filament\AccionFilament;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pestaña «Cotizaciones» de la oportunidad (D19), también capa del despiece de su caja (clave `cotizaciones`): sus
 * versiones, la más reciente primero, y «Nueva cotización de esta oportunidad» mientras esté abierta (D4).
 */
final class CotizacionesDeOportunidad extends ComponentePestanaOportunidad implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use TablaDeCotizaciones;

    public function table(Table $table): Table
    {
        return $this->tablaDeCotizaciones($table, fn (Builder $query): Builder => $query->where('oportunidad_id', $this->oportunidadId), false)
            ->headerActions([
                AccionFilament::desde('crm.cotizaciones.crear', fn (): array => ['oportunidad_id' => $this->oportunidadId], function (mixed $cotizacion): void {
                    Notification::make()->success()->title('Cotización creada, en borrador.')->send();

                    if ($cotizacion instanceof Cotizacion) {
                        $this->redirect(VerCotizacion::getUrl(['record' => $cotizacion->id]), navigate: true);
                    }
                }, controles: ['contacto_id' => fn () => SelectoresCotizacion::contacto(oportunidadFija: $this->oportunidadId)])
                    ->name('nuevaCotizacion')->label('Nueva cotización de esta oportunidad')->icon('heroicon-o-plus')->slideOver()
                    ->hidden(fn (): bool => ! app(OportunidadesService::class)->resumen($this->oportunidadId)->abierta),
            ])
            ->emptyStateDescription('Créala con «Nueva cotización de esta oportunidad».');
    }

    public function render(): View
    {
        return view('cotizaciones::tabla');
    }
}
