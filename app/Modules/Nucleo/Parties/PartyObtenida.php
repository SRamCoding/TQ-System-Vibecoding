<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Resultado de PartiesService::obtenerOCrear(). Si ya existía desactivada, vuelve con `activa = false` y sin
 * reactivarse: decide quien llama (plan 1.3, D6).
 */
final readonly class PartyObtenida
{
    public function __construct(
        public int $id,
        public string $etiqueta,
        public bool $activa,
        public bool $creada,
    ) {}
}
