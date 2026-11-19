<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * MVP rápido (t1-puente-erp, MV-006; ADR 0023): al ganar, en la misma transacción, nace el proyecto con su presupuesto
 * copiado de lo cotizado. Lo implementa el ERP; sin ERP, no hace nada. Si falla, la oportunidad no se gana.
 */
interface ProyectoAlGanar
{
    public function nacer(int $oportunidadId, string $nombre, int $clientePartyId, ?CotizacionAceptada $aceptada, DatosDelProyectoAlGanar $datos): void;

    /** Al anular la ganada: anula su proyecto, o lo rechaza si ya tiene costos o avanzó. */
    public function anular(int $oportunidadId, string $motivo): void;
}
