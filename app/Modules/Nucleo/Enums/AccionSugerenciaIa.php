<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Qué hizo la persona con una sugerencia de la IA (plan 1.11, D9). Mide cuánto acierta cada proceso. */
enum AccionSugerenciaIa: string
{
    case Aceptada = 'aceptada';
    case Corregida = 'corregida';
    case Rechazada = 'rechazada';
}
