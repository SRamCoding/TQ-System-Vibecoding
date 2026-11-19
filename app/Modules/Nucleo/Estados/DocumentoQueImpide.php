<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

/**
 * Un documento que impide anular otro, como lo nombra el mensaje: «la recepción ING-2026-0031 (Confirmada)». El
 * identificador sale de `DefinicionDocumento::identificador()`: el número, o `#id` si el tipo no se numera (plan 1.6).
 */
final readonly class DocumentoQueImpide
{
    /**
     * @param  string  $tipo  con su artículo, en minúsculas: «la recepción»
     */
    public function __construct(
        public string $tipo,
        public string $identificador,
        public string $estado,
    ) {}

    public function descripcion(): string
    {
        return "{$this->tipo} {$this->identificador} ({$this->estado})";
    }
}
