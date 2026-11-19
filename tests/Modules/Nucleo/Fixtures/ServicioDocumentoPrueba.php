<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use Illuminate\Support\Facades\DB;

/** Servicio de un módulo inventado que edita el documento de prueba y sus líneas, como lo haría uno real. */
final class ServicioDocumentoPrueba
{
    public function renombrar(int $documentoId, string $nombre): DocumentoPrueba
    {
        return DB::transaction(function () use ($documentoId, $nombre): DocumentoPrueba {
            $documento = DocumentoPrueba::query()->lockForUpdate()->findOrFail($documentoId);
            $documento->nombre = $nombre;
            $documento->save();

            return $documento;
        });
    }

    public function agregarLinea(int $documentoId, string $descripcion, string $cantidad): LineaDocumentoPrueba
    {
        return LineaDocumentoPrueba::query()->create(['documento_prueba_id' => $documentoId, 'descripcion' => $descripcion, 'cantidad' => $cantidad]);
    }
}
