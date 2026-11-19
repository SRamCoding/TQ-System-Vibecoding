<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Numeracion;

/** Cuándo vuelve a 1 el contador de una serie (plan 1.6, D3). */
enum ReinicioNumeracion: string
{
    /** `REQ-2026-0001`: cada 1 de enero. */
    case Anual = 'anual';

    /** `AS-2026-10-0001`: cada primero de mes. */
    case Mensual = 'mensual';

    /** `COL-0001`: nunca. */
    case Nunca = 'nunca';
}
