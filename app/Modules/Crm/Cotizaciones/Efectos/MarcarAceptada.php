<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/** D5 y D7: la fecha y el respaldo de la aceptación, y la marca de aceptada única (por número y por oportunidad, en la base). */
final readonly class MarcarAceptada implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion) {
            return [];
        }

        [$fecha, $tipo, $referencia, $adjuntoId] = app(PedidoAceptacion::class)->pedido();
        $documento->es_aceptada = true;
        $documento->fecha_aceptacion = $fecha;
        $documento->respaldo_tipo = $tipo;
        $documento->respaldo_referencia = $referencia;
        $documento->respaldo_adjunto_id = $adjuntoId;

        try {
            $documento->save();
        } catch (UniqueConstraintViolationException) {
            throw CotizacionRechazada::conMotivo('La oportunidad ya tiene otra cotización aceptada: anula esa aceptación primero.');
        }

        return [];
    }

    public function revertir(Model $documento, array $datos): void
    {
        $documento->forceFill(['es_aceptada' => false, 'fecha_aceptacion' => null, 'respaldo_tipo' => null, 'respaldo_referencia' => null, 'respaldo_adjunto_id' => null]);
        $documento->save();
    }
}
