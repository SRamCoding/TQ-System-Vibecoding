<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lee el texto por página con `smalot/pdfparser` (ADR 0019: LGPL-3.0, se usa sin modificarla; nunca se copia ni se
 * parcha: un arreglo va aquí). Con límite de tamaño y de memoria; si no puede leerlo, nulo y el proceso sigue (D6).
 */
final class TextoDePdf implements TextoDeAdjunto
{
    /** Más grande no se lee: la evidencia queda en seguridad baja. */
    public const int MAXIMO_BYTES = 20 * 1024 * 1024;

    /** Memoria máxima al descomprimir cada objeto del PDF. */
    public const int MEMORIA_BYTES = 64 * 1024 * 1024;

    public function paginas(string $pdf): ?array
    {
        if ($pdf === '' || strlen($pdf) > self::MAXIMO_BYTES) {
            return null;
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(self::MEMORIA_BYTES);
            $paginas = (new Parser([], $config))->parseContent($pdf)->getPages();

            return array_values(array_map(fn (Page $pagina): string => $pagina->getText(), $paginas));
        } catch (Throwable $error) {
            // Sin el contenido ni el mensaje: pueden traer datos del documento.
            Log::info('No se pudo leer el texto de un PDF para la IA.', ['error' => $error::class]);

            return null;
        }
    }
}
