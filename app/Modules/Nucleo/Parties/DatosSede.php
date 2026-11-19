<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Lo que se escribe de una sede al agregarla o editarla (plan 1.3b, D3 y D4). Las coordenadas llegan como texto
 * (`-6.7714`), nunca `float` (regla 4); el contacto, si llega, es un vínculo vigente de la misma party.
 */
final readonly class DatosSede
{
    public function __construct(
        public string $nombre,
        public string $direccion,
        public ?int $ubigeoId = null,
        public ?string $latitud = null,
        public ?string $longitud = null,
        public ?int $contactoId = null,
        public ?string $codigoEstablecimiento = null,
    ) {}
}
