<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

use Illuminate\Database\Query\Builder;

/**
 * El aporte que da las oportunidades abiertas (CRM-02) también sirve para filtrar en la base (plan 2.20, D11). El
 * aporte nombra solo su tabla: devuelve una subconsulta y Cuentas la compone (plan 2.21, D13.4).
 */
interface AporteOportunidadesAbiertas extends AporteActividad
{
    /** Subconsulta sobre la tabla del aporte que selecciona `cuenta_id` de las cuentas con al menos una abierta. */
    public function cuentasConAbiertas(): Builder;
}
