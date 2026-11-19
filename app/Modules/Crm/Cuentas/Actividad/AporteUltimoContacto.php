<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

use Illuminate\Database\Query\Builder;

/**
 * El aporte que da el último contacto (Actividades, CRM-05) también sirve para filtrar y ordenar en la base, sin traer
 * todo a PHP (plan 2.20, D11): la pestaña y el aviso «Sin actividad» y la columna ordenable. El aporte nombra solo su
 * tabla: devuelve una subconsulta y Cuentas hace el cruce (plan 2.21, D13.4).
 */
interface AporteUltimoContacto extends AporteActividad
{
    /**
     * Subconsulta sobre la tabla del aporte con dos columnas: `cuenta_id` y `ultimo_contacto` (fecha), una fila por
     * cuenta que tuvo algún contacto. Una cuenta sin contacto no aparece.
     */
    public function ultimosContactos(): Builder;
}
