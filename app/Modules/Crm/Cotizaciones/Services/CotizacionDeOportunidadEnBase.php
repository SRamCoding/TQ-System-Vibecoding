<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Services\CotizacionAceptada;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\VersionDeCotizacion;
use App\Modules\Nucleo\Models\Moneda;

/** La real del puerto de Oportunidades (plan 2.22, D12; C3): la versión aceptada de la oportunidad, si la hay. */
final readonly class CotizacionDeOportunidadEnBase implements CotizacionDeOportunidad
{
    public function aceptada(int $oportunidadId): ?CotizacionAceptada
    {
        $c = Cotizacion::query()->where('oportunidad_id', $oportunidadId)->where('es_aceptada', true)->first();

        if ($c === null) {
            return null;
        }

        $resumen = app(CotizacionesService::class)->resumen($c->id);

        return new CotizacionAceptada($c->id, $resumen->identificador, $resumen->total, $resumen->moneda, $resumen->plazo, $resumen->formaPago, $resumen->garantia, $c->presupuesto_id);
    }

    public function versiones(int $oportunidadId): array
    {
        return Cotizacion::query()->with('estado')->where('oportunidad_id', $oportunidadId)
            ->whereHas('estado', fn ($query) => $query->where('codigo', '!=', DefinicionCotizacion::ANULADA))
            ->orderBy('id')->get()
            ->map(fn (Cotizacion $c): VersionDeCotizacion => new VersionDeCotizacion(
                $c->id, "{$c->numero} v{$c->numero_version}", $c->numero, $c->numero_version, $c->estado->nombre,
                (string) $c->total, (string) Moneda::query()->whereKey($c->moneda_id)->value('simbolo'), $c->emitida_at, $c->margen_porcentaje === null ? null : (string) $c->margen_porcentaje, $c->margen_minimo === null ? null : (string) $c->margen_minimo,
                (bool) $c->es_aceptada, $c->presupuesto_id, VerCotizacion::getUrl(['record' => $c->id]),
            ))->values()->all();
    }
}
