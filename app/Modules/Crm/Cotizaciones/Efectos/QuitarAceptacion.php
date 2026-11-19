<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/** D5 y D7: al anular la aceptación, vuelve a emitida sin la marca ni el respaldo (quedan en `datos` y en el historial). */
final readonly class QuitarAceptacion implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion) {
            return [];
        }

        $antes = [
            'fecha_aceptacion' => $documento->fecha_aceptacion?->toDateString(),
            'respaldo_tipo' => $documento->respaldo_tipo?->value,
            'respaldo_referencia' => $documento->respaldo_referencia,
            'respaldo_adjunto_id' => $documento->respaldo_adjunto_id,
        ];
        $documento->es_aceptada = false;
        $documento->fecha_aceptacion = null;
        $documento->respaldo_tipo = null;
        $documento->respaldo_referencia = null;
        $documento->respaldo_adjunto_id = null;
        $documento->save();

        return $antes;
    }

    public function revertir(Model $documento, array $datos): void
    {
        // «Anular aceptación» no es una cancelación: el núcleo no la revierte.
    }
}
