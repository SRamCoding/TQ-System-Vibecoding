<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Enums\Width;

/**
 * La ventana con la tabla de un lugar (una etapa abierta, Ganadas o Perdidas): `mountAction('verLugar', {lugar})`. La
 * abren los terminales de la faja (plan 2.21b, pedido B5) y el círculo del centro de cada etapa del tablero de gemas
 * (plan 2.21c, D2). Es `TablaDeLaFaja`: buscador, orden por columna y páginas. El título dice lo mismo que el terminal o
 * el círculo («N · S/ total», de `totalDeLugarDeLaFaja`). Si la página filtra un lugar («Mías» en Ganadas y Perdidas
 * del tablero, plan 2.21c, D15), `soloDeEnLaTabla` lo dice y la tabla y su total lo siguen, con su propio orden.
 */
trait AbreLaTablaDeUnLugar
{
    public function verLugarAction(): Action
    {
        return Action::make('verLugar')
            ->modalHeading(fn (array $arguments): string => self::lugarDe($arguments)->etiqueta())
            ->modalDescription(function (array $arguments): string {
                $lugar = self::lugarDe($arguments);
                $soloDe = $this->soloDeEnLaTabla($lugar);
                $total = app(OportunidadesService::class)->totalDeLugarDeLaFaja($lugar, $soloDe);

                return app(FajaDeVidrio::class)->textoDelTotal($total).($lugar->esSala() ? ' · del período de cierre' : '').($soloDe === null ? '' : ' · solo las mías');
            })
            ->schema(function (array $arguments): array {
                $lugar = self::lugarDe($arguments);
                $soloDe = $this->soloDeEnLaTabla($lugar);

                return [
                    Livewire::make(TablaDeLaFaja::class, ['lugar' => $lugar->value, 'soloDe' => $soloDe])
                        ->key('faja-tabla-'.$lugar->value.'-'.($soloDe ?? 'todas')),
                ];
            })
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    /** El ejecutivo al que se limita la tabla de un lugar, o nulo para todas. La faja muestra todas. */
    protected function soloDeEnLaTabla(LugarFaja $lugar): ?int
    {
        return null;
    }

    /** @param  array<string, mixed>  $arguments */
    private static function lugarDe(array $arguments): LugarFaja
    {
        $lugar = LugarFaja::tryFrom((string) ($arguments['lugar'] ?? ''));
        abort_if($lugar === null, 404);

        return $lugar;
    }
}
