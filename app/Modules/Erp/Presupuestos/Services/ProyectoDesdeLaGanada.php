<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\CotizacionAceptada;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\ProyectoAlGanar;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\UsuarioSistema;

/**
 * MVP rápido (t1-puente-erp, MV-006; ADR 0023, D2): al ganar nace el proyecto «en preparación» y lo cotizado se copia
 * como su presupuesto aprobado y vigente. Corre dentro de la transacción de ganar: si algo falla, no se gana.
 */
final readonly class ProyectoDesdeLaGanada implements ProyectoAlGanar
{
    public function nacer(int $oportunidadId, string $nombre, int $clientePartyId, ?CotizacionAceptada $aceptada, DatosDelProyectoAlGanar $datos): void
    {
        if ($aceptada === null) {
            throw PresupuestoRechazado::conMotivo('Para ganar, la oportunidad necesita su cotización aceptada: de ella sale el presupuesto del proyecto.');
        }

        $quien = auth()->user() instanceof User ? auth()->user() : User::query()->findOrFail(app(UsuarioSistema::class)->id());
        $proyecto = app(ProyectosService::class)->crearDesdeOportunidad($oportunidadId, new DatosProyecto(
            $nombre, $datos->jefeProyectoUserId, $datos->fechaInicio, $datos->fechaFinPrevista, $clientePartyId,
        ), $quien);

        app(ArmadoPresupuestoService::class)->copiarAlProyecto($aceptada->presupuestoId, $aceptada->lineasLibres, $proyecto->id, $aceptada->id, $nombre, $quien);

        // MVP rápido (t2-contratos): el contrato nace en borrador con el cliente, el monto y las condiciones de la cotización.
        $contrato = app(ContratosService::class)->crearAlGanar(
            $proyecto->id, $oportunidadId, $clientePartyId, $nombre, app(CuentasService::class)->deParty($clientePartyId)?->esPublica ?? false,
            $aceptada->total, $datos->fechaInicio, $datos->fechaFinPrevista, $aceptada->formaPago,
            $aceptada->garantia === null ? null : (int) $aceptada->garantia,
        );
        app(ProyectosService::class)->vincularContrato($proyecto->id, $contrato->id);
    }

    public function anular(int $oportunidadId, string $motivo): void
    {
        // Primero el contrato (si ya está vigente, no se deshace la ganada); después el proyecto.
        app(ContratosService::class)->anularDeOportunidad($oportunidadId);
        app(ProyectosService::class)->anularDesdeOportunidad($oportunidadId, $motivo);
    }
}
