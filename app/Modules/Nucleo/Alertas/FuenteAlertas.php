<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Alertas;

use Carbon\CarbonImmutable;

/**
 * Lo que revisa la revisión diaria de alertas (plan 1.10, D4) por un módulo: busca sus hechos de hoy y los emite con
 * `AlertasService::emitir()`, que no repite uno ya emitido. Usa solo los servicios de su propio módulo (regla 2).
 */
interface FuenteAlertas
{
    public function revisar(CarbonImmutable $hoy): void;
}
