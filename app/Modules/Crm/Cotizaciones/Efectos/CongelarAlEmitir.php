<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Al emitir (D9, D10): la fecha de emisión, el vencimiento (emisión + días de validez, calendario), el tipo de cambio
 * venta del día y la tasa del IGV quedan en la versión y nunca se vuelven a leer.
 */
final readonly class CongelarAlEmitir implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion) {
            return [];
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy(), 'America/Lima');
        $documento->igv_porcentaje = app(ParametrosService::class)->decimal(CotizacionesService::PARAMETRO_IGV);
        $documento->tipo_cambio = app(TipoCambioService::class)->del('USD', $hoy)->venta;
        $documento->fecha_emision = $hoy;
        $documento->fecha_vencimiento = $hoy->addDays($documento->dias_validez);
        $documento->emitida_at = CarbonImmutable::now();
        // Los totales se calcularon en borrador con la tasa del parámetro; si cambió desde entonces, no se emite con otra.
        $copia = clone $documento;
        app(CotizacionesService::class)->ponerTotales($copia);

        if ($copia->total !== $documento->total) {
            throw CotizacionRechazada::conMotivo('La tasa del IGV cambió desde que se calculó la cotización: vuelve a borrador para recalcularla.');
        }

        $documento->save();

        return ['fecha_vencimiento' => $documento->fecha_vencimiento->toDateString(), 'tipo_cambio' => (string) $documento->tipo_cambio];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Una emitida no se anula (D5): salió al cliente. Nada que deshacer.
    }
}
