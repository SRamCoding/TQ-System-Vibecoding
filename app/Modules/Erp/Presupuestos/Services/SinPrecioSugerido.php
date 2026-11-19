<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use Carbon\CarbonImmutable;

/** Mientras no exista C5 (plan 2.9, D1): nunca hay sugerencia; el precio lo escribe quien presupuesta. */
final readonly class SinPrecioSugerido implements PrecioSugeridoManoObra
{
    public function vigente(int $categoriaId, CarbonImmutable $fecha): ?string
    {
        return null;
    }
}
