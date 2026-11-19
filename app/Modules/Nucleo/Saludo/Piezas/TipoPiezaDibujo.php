<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo\Piezas;

/** Las tres capas de un dibujo por piezas (plan 1.4e2): de atrás hacia adelante. */
enum TipoPiezaDibujo: string
{
    case Fondo = 'fondo';
    case Motivo = 'motivo';
    case Acompanante = 'acompanante';
}
