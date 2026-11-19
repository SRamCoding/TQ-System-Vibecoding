<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historial;

use Carbon\CarbonImmutable;

/** Una línea de la pestaña de historial, ya legible. */
final readonly class FilaHistorial
{
    public function __construct(
        public CarbonImmutable $fecha,
        public string $autor,
        public string $evento,
        public string $sujeto,
        public string $campo,
        public string $anterior,
        public string $nuevo,
        /** Si el cambio se lee en una sola frase, sin detalle por campo. */
        public ?string $resumen = null,
    ) {}
}
