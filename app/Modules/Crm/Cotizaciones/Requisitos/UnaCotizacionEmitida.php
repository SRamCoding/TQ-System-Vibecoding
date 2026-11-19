<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Requisitos;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Requisitos\FormaDeCumplir;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitoApagable;
use App\Modules\Crm\Oportunidades\Requisitos\SeCumpleEnLaCompuerta;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Para «Propuesta enviada», una cotización que llegó a emitirse, aunque luego venciera o la rechazaran (plan 2.22, D11).
 * Se apaga con su parámetro sin tocar código (pendiente «Embudo comercial»): apagado, no se exige ni se muestra.
 */
final readonly class UnaCotizacionEmitida implements RequisitoApagable, SeCumpleEnLaCompuerta
{
    public function exigidoAlEntrarEn(): string
    {
        return 'propuesta_enviada';
    }

    public function texto(): string
    {
        return 'Una cotización emitida';
    }

    public function activo(): bool
    {
        return app(ParametrosService::class)->booleano(CotizacionesServiceProvider::PARAMETRO_EXIGIR_EMITIDA);
    }

    public function cumple(int $oportunidadId): bool
    {
        return Cotizacion::query()->where('oportunidad_id', $oportunidadId)->whereNotNull('emitida_at')->exists();
    }

    /** Plan 2.21d, D8: emitir ahí mismo la última cotización aprobada; si no hay, ir al costeo a prepararla. */
    public function formaDeCumplir(int $oportunidadId): FormaDeCumplir
    {
        $aprobada = Cotizacion::query()->where('oportunidad_id', $oportunidadId)
            ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionCotizacion::APROBADA))
            ->orderByDesc('id')->first();

        return $aprobada === null
            ? FormaDeCumplir::enlace('Preparar la cotización', CotizacionResource::getUrl())
            : FormaDeCumplir::conAccion("Emitir {$aprobada->numero}", DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::EMITIR, ['cotizacion_id' => $aprobada->id, 'version' => $aprobada->version]);
    }
}
