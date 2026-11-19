<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Cómo terminó una corrida de una tarea del programador (plan 1.12, D1), con su CHECK en la base. */
enum ResultadoEjecucion: string
{
    case EnCurso = 'en_curso';
    case Correcta = 'correcta';
    case Fallida = 'fallida';
}
