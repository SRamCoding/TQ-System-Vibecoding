<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Una sugerencia de la IA (plan 1.11, D9), con su CHECK en la base. */
enum EstadoSugerenciaIa: string
{
    case PorRevisar = 'por_revisar';
    case Resuelta = 'resuelta';
}
