<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Efectos;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Events\OportunidadGanada;
use App\Modules\Crm\Oportunidades\Events\OportunidadGanadaAnulada;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\PedidoGanar;
use App\Modules\Crm\Oportunidades\Services\ProyectoAlGanar;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Models\HistorialEstado;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ganar avisa al ERP con `OportunidadGanada` (C3; plan 2.21, D3), que sale después de confirmar. Revertir (anular una
 * ganada, D1) llama a Contratos dentro de la transacción: anula su borrador y el proyecto en preparación, o falla si el
 * contrato ya avanzó y se deshace todo. Después de confirmar sale `OportunidadGanadaAnulada`, como aviso.
 */
final readonly class GanadaAvisaAlErp implements Efecto
{
    public function aplicar(Model $documento): array
    {
        /** @var Oportunidad $documento */
        $cuenta = app(CuentasService::class)->resumen($documento->cuenta_id);
        // Plan 2.22, D12: con cotización aceptada, el monto, la moneda y las condiciones salen de ella.
        $aceptada = app(CotizacionDeOportunidad::class)->aceptada($documento->id);

        // MV-006: el proyecto nace aquí, en la transacción de ganar. Sin datos de «Ganada» (otro camino), el ejecutivo
        // queda de jefe y las fechas salen del cierre y del plazo de la cotización.
        $cierre = CarbonImmutable::parse((string) $documento->fecha_cierre_real?->toDateString());
        $inicio = $cierre->addDays(7);
        $datos = app(PedidoGanar::class)->datos() ?? new DatosDelProyectoAlGanar($documento->ejecutivo_user_id, $inicio, $inicio->addDays(90));
        app(ProyectoAlGanar::class)->nacer($documento->id, $documento->nombre, $cuenta->partyId, $aceptada, $datos);

        OportunidadGanada::dispatch(
            $documento->id,
            $documento->cuenta_id,
            $cuenta->partyId,
            $aceptada->total ?? (string) $documento->monto_estimado,
            $aceptada->moneda ?? $documento->moneda->codigo,
            (string) $documento->fecha_cierre_real?->toDateString(),
            $documento->ejecutivo_user_id,
            $aceptada?->id,
            $aceptada?->plazo,
            $aceptada?->formaPago,
            $aceptada?->garantia,
            $aceptada?->presupuestoId,
        );

        return ['cuenta_id' => $documento->cuenta_id];
    }

    public function revertir(Model $documento, array $datos): void
    {
        $motivo = $this->motivoDeLaAnulacion($documento);
        app(ProyectoAlGanar::class)->anular((int) $documento->getKey(), $motivo);
        app(ContratosDeOportunidad::class)->anularBorrador((int) $documento->getKey(), $motivo);

        OportunidadGanadaAnulada::dispatch((int) $documento->getKey(), (int) $datos['cuenta_id'], $motivo);
    }

    /** El motivo de la fila del historial que está anulando: la más reciente de esta oportunidad. */
    private function motivoDeLaAnulacion(Model $documento): string
    {
        $fila = HistorialEstado::query()
            ->where('documentable_type', $documento->getMorphClass())
            ->where('documentable_id', $documento->getKey())
            ->latest('id')
            ->first();

        $nombre = $fila?->motivo->nombre ?? 'Sin motivo';

        return $fila?->motivo_es_otro ? "{$nombre}: {$fila->motivo_detalle}" : $nombre;
    }
}
