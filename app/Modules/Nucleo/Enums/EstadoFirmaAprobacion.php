<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Una firma dentro de su ronda (plan 1.7), con su CHECK en la base. */
enum EstadoFirmaAprobacion: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Observada = 'observada';

    /** Su ronda se cerró sin que firmara (otro observó, o el documento cambió). */
    case Anulada = 'anulada';
}
