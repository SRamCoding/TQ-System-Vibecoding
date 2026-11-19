<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Cómo terminó un pedido de aprobación (plan 1.7, D4 y D5), con su CHECK en la base. */
enum EstadoRondaAprobacion: string
{
    case Abierta = 'abierta';

    /** Firmaron todos: la transición se ejecutó. */
    case Completa = 'completa';

    /** Un firmante observó: el documento volvió a editable. */
    case Observada = 'observada';

    /** El documento cambió o se reabrió: las firmas dejaron de valer. */
    case Anulada = 'anulada';
}
