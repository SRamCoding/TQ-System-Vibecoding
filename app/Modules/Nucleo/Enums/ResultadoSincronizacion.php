<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Qué pasó al traer el tipo de cambio de la fuente automática. */
enum ResultadoSincronizacion: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case SinCambios = 'sin_cambios';
    /** Ya había un valor registrado a mano para ese día: manda la persona. */
    case SeRespetaManual = 'se_respeta_manual';

}
