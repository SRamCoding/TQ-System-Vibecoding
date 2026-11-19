<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

/** El color de un recuadro de la página de salud (plan 1.12, D2). */
enum EstadoSalud: string
{
    /** Todo en orden. */
    case Bien = 'bien';

    /** Funciona, pero hay algo que atender (por ejemplo, un respaldo que solo está en el servidor). */
    case Aviso = 'aviso';

    /** Algo falló o no corrió a tiempo. */
    case Falla = 'falla';

    /** Todavía no hay con qué juzgar (nunca corrió, no está configurado). */
    case SinDatos = 'sin_datos';
}
