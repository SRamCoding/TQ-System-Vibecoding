<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

/**
 * Contrato de un modelo de catálogo (M5). La implementación por defecto está en EsCatalogo.
 */
interface ElementoCatalogo
{
    public static function columnaActivo(): string;

    public function etiqueta(): string;

    public function estaActivo(): bool;
}
