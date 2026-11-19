<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

use App\Modules\Nucleo\Models\User;

/**
 * El punto de color de la tarjeta de la portada (plan 2.61, D10 y D11): cómo va el costo del proyecto (con el contrato,
 * el margen). Lo implementa Presupuestos; sin él, ninguna tarjeta lleva semáforo. Solo se pide para quien ve costos.
 */
interface SemaforoDeCosto
{
    /**
     * @param  list<int>  $proyectos
     * @return array<int, string> por proyecto: `success`, `warning` o `danger`; los que no tienen dato (o la persona no
     *                            puede verlo), no van
     */
    public function tonos(array $proyectos, User $usuario): array;
}
