<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/** Sin el ERP: ganar no crea nada. */
final class SinProyectoAlGanar implements ProyectoAlGanar
{
    public function nacer(int $oportunidadId, string $nombre, int $clientePartyId, ?CotizacionAceptada $aceptada, DatosDelProyectoAlGanar $datos): void {}

    public function anular(int $oportunidadId, string $motivo): void {}
}
