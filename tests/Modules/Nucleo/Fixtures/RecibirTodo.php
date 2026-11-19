<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/**
 * Efecto de prueba que hace de «otro documento que recibe»: llena los campos derivados de las líneas y la cabecera
 * aunque el documento ya no sea editable. Revertir los deja como estaban.
 */
final class RecibirTodo implements Efecto
{
    public function aplicar(Model $documento): array
    {
        $antes = [];

        foreach (LineaDocumentoPrueba::query()->where('documento_prueba_id', $documento->getKey())->orderBy('id')->get() as $linea) {
            $antes[(string) $linea->id] = $linea->cantidad_recibida;
            $linea->cantidad_recibida = $linea->cantidad;
            $linea->save();
        }

        $documento->setAttribute('total_recibido', (string) LineaDocumentoPrueba::query()->where('documento_prueba_id', $documento->getKey())->sum('cantidad'));
        $documento->save();

        return ['lineas' => $antes];
    }

    public function revertir(Model $documento, array $datos): void
    {
        foreach ($datos['lineas'] as $id => $cantidad) {
            $linea = LineaDocumentoPrueba::query()->findOrFail($id);
            $linea->cantidad_recibida = $cantidad;
            $linea->save();
        }

        $documento->setAttribute('total_recibido', '0.00');
        $documento->save();
    }
}
