<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

/**
 * Lo que se dibuja en un gran momento (plan 1.4d, D5 y D7): la variante, el versículo con su fuente (o ninguno) y el
 * año. Sale del servicio del gran momento; la vista solo lo pinta.
 */
final readonly class EscenaGranMomento
{
    public function __construct(
        public VarianteGranMomento $variante,
        public int $anio,
        public ?string $versiculo = null,
        public ?string $fuente = null,
    ) {}
}
