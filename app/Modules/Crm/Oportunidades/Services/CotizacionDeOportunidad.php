<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * La cotización aceptada de una oportunidad (plan 2.22, D12; C3). Oportunidades no lee las tablas de Cotizaciones:
 * pregunta aquí. Cotizaciones registra la real; mientras no exista vale `CotizacionesSinModulo` («no hay»), como
 * `ContratosDeOportunidad`.
 */
interface CotizacionDeOportunidad
{
    public function aceptada(int $oportunidadId): ?CotizacionAceptada;

    /**
     * Todas las versiones de sus cotizaciones (sin las anuladas), de la más antigua a la más nueva (plan 2.21d, D10).
     *
     * @return list<VersionDeCotizacion>
     */
    public function versiones(int $oportunidadId): array;
}
