<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Requisitos;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Requisitos\FormaDeCumplir;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitoApagable;
use App\Modules\Crm\Oportunidades\Requisitos\SeCumpleEnLaCompuerta;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Para ganar, una cotización aceptada con su documento de respaldo (plan 2.22, D11; P2). Se apaga con su parámetro;
 * apagado, no se exige ni se muestra.
 */
final readonly class UnaCotizacionAceptada implements RequisitoApagable, SeCumpleEnLaCompuerta
{
    public function exigidoAlEntrarEn(): string
    {
        return 'ganada';
    }

    public function texto(): string
    {
        return 'Una cotización aceptada, con su documento de respaldo';
    }

    public function activo(): bool
    {
        return app(ParametrosService::class)->booleano(CotizacionesServiceProvider::PARAMETRO_EXIGIR_ACEPTADA);
    }

    public function cumple(int $oportunidadId): bool
    {
        return Cotizacion::query()->where('oportunidad_id', $oportunidadId)->where('es_aceptada', true)->exists();
    }

    /**
     * Plan 2.21d, D8: aceptar pide la fecha y el documento de respaldo (acta u orden) con su archivo, que se adjunta a la
     * cotización: la compuerta lleva a la emitida, donde está «Aceptar».
     */
    public function formaDeCumplir(int $oportunidadId): ?FormaDeCumplir
    {
        $emitida = Cotizacion::query()->where('oportunidad_id', $oportunidadId)
            ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionCotizacion::EMITIDA))
            ->orderByDesc('id')->first();

        return $emitida === null ? null : FormaDeCumplir::enlace("Aceptar {$emitida->numero} con su acta u orden", VerCotizacion::getUrl(['record' => $emitida->id]));
    }
}
