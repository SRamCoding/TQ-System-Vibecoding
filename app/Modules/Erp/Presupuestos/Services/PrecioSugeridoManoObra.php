<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use Carbon\CarbonImmutable;

/**
 * El punto de extensión del precio de la mano de obra en el APU (plan 2.9, D1). Recibe lo mismo que
 * `CostoHoraService::vigente(categoriaId, fecha)` de C5 (ADR 0020), para que C5 se enchufe sin adaptadores. Es un
 * precio sugerido, no obligatorio: quien presupuesta lo puede cambiar, y el APU guarda el que quedó como foto. Hoy,
 * mientras C5 no exista, la versión registrada es `SinPrecioSugerido`.
 */
interface PrecioSugeridoManoObra
{
    /** El costo por hora de la categoría a esa fecha, como texto decimal; null si no hay sugerencia. */
    public function vigente(int $categoriaId, CarbonImmutable $fecha): ?string;
}
