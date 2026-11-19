<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * De dónde vino un distrito (M5, plan 1.2b E7). La actualización desde el INEI solo desactiva los
 * suyos: un distrito agregado a mano no está en el padrón y no debe apagarse. Con su CHECK en la base.
 */
enum OrigenUbigeo: string
{
    case Inei = 'inei';
    case Manual = 'manual';
}
