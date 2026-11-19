<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Carbon\CarbonImmutable;

/**
 * Un detalle escrito en «Otro», agrupado por su texto normalizado (`clave`), con cuántas veces se escribió y cuándo
 * fue la última (plan 1.5, D10, agregado 2). `texto` es la forma más reciente, para mostrar.
 */
final readonly class DetalleDeOtro
{
    public function __construct(
        public string $clave,
        public string $texto,
        public int $veces,
        public CarbonImmutable $ultimoUso,
    ) {}
}
