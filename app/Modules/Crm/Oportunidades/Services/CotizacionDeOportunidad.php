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

    /**
     * Observación 9.5 (1): el margen previsto de cada oportunidad, de su cotización vigente (la aceptada o, si no, la
     * última versión sin anular ni reemplazar), con el mínimo del parámetro de hoy. Sin cotización, no aparece.
     *
     * @param  list<int>  $oportunidadIds
     * @return array<int, array{porcentaje: ?string, monto: string, minimo: string, porAprobar: bool, cotizacion: string}>
     */
    public function margenes(array $oportunidadIds): array;
}
