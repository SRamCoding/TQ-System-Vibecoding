<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Services\NavegacionService;

/**
 * El área de una persona para el saludo (plan 1.4b, D3): CRM o ERP según los módulos que tiene permitidos (NavegacionService, plan 1.4f): ocultar un
 * módulo de su barra no cambia su saludo. Si ve
 * de los dos lados o de ninguno, no tiene área. Es la única pieza que cambiaría si un día el área sale de la unidad
 * de su puesto (mejora anotada en el plan).
 */
final readonly class AreaDeUsuario
{
    public function __construct(private NavegacionService $navegacion) {}

    public function de(User $usuario): ?AreaModulo
    {
        $areas = array_unique(array_map(
            fn (CajaModulo $caja): string => $caja->area->value,
            // Solo cuentan CRM y ERP: el Núcleo y «Mien» (Personal, plan 1.4l) los ve cualquiera.
            array_filter($this->navegacion->permitidos($usuario), fn (CajaModulo $caja): bool => in_array($caja->area, [AreaModulo::Crm, AreaModulo::Erp], true)),
        ));

        return count($areas) === 1 ? AreaModulo::from(reset($areas)) : null;
    }
}
