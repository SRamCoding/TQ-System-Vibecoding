<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

/** Lo que otros módulos pueden decir de una cuenta (plan 2.20, D11). Mientras nadie lo aporte, la pantalla no lo muestra. */
enum DatoActividad: string
{
    /** Oportunidades (CRM-02). */
    case OportunidadesAbiertas = 'oportunidades_abiertas';
    /** Actividades (CRM-05). */
    case UltimoContacto = 'ultimo_contacto';
    /** Actividades (CRM-05). */
    case ProximoContacto = 'proximo_contacto';
    /** Facturación (etapa 3). */
    case Facturado = 'facturado';
}
