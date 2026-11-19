<?php

declare(strict_types=1);

namespace Tests\Modules\Crm\Cuentas\Fixtures;

use App\Modules\Crm\Cuentas\Pestanas\ComponentePestanaCuenta;

/** Una pestaña de cuenta de prueba, como la que sumará Oportunidades. */
final class PestanaCuentaPrueba extends ComponentePestanaCuenta
{
    public function render(): string
    {
        return '<div>Oportunidades de la cuenta {{ $cuentaId }}</div>';
    }
}
