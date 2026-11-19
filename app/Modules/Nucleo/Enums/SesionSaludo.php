<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * En qué sesión del día está la persona (plan 1.4aa, D8), según sus saludos anteriores (`saludos_mostrados`) y sus
 * cambios de hoy (`auditoria_cambios`). Un saludo con esta condición sale solo en esa clase de sesión.
 */
enum SesionSaludo: string
{
    /** Sin otra sesión antes, hoy. */
    case PrimeraDelDia = 'primera_del_dia';
    /** Ya entró hoy y todavía no cambió nada. */
    case Vuelve = 'vuelve';
    /** Ya entró hoy y ya hizo cambios (la bitácora tiene alguno suyo de hoy). */
    case VuelveConActividad = 'vuelve_con_actividad';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PrimeraDelDia => 'Primera sesión del día',
            self::Vuelve => 'Vuelve el mismo día',
            self::VuelveConActividad => 'Vuelve el mismo día, después de trabajar',
        };
    }
}
