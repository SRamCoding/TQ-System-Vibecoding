<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Nucleo\Parties\ResumenParty;

/** La cuenta recién creada y, si la ficha también es nueva y es un RUC 10, su posible pareja con DNI (D2, sin bloquear). */
final readonly class CuentaCreada
{
    public function __construct(
        public ResumenCuenta $cuenta,
        public bool $fichaNueva,
        public ?ResumenParty $posibleMismaPersona = null,
    ) {}
}
