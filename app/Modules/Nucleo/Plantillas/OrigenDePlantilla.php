<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Plantillas;

/** De qué plantilla y versión salió un documento: «Árbol creado desde Videovigilancia v3» (ADR 0022, D3). */
final readonly class OrigenDePlantilla
{
    public function __construct(
        public int $plantillaId,
        public string $nombre,
        public int $numero,
    ) {}

    public function texto(): string
    {
        return "{$this->nombre} v{$this->numero}";
    }
}
