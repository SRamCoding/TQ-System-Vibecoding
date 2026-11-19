<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * De dónde vino un tipo de cambio (M5). La carga automática desde SUNAT está POR VALIDAR; hoy solo
 * hay carga manual. Con su CHECK en la base.
 */
enum OrigenTipoCambio: string
{
    case Sunat = 'sunat';
    case Manual = 'manual';
}
