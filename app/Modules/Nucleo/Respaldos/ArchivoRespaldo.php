<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

/** El archivo que dejó un respaldo en su destino (plan 1.12, D3): su nombre y su tamaño, nunca su contenido. */
final readonly class ArchivoRespaldo
{
    public function __construct(
        public string $nombre,
        public int $bytes,
    ) {}
}
