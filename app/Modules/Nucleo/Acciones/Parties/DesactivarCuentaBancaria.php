<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Deja de usarse para pagar o cobrar y pierde la marca de principal. Avisa por evento. */
final class DesactivarCuentaBancaria extends AccionCuentaBancaria
{
    protected function operacion(): string
    {
        return 'desactivar-cuenta-bancaria';
    }

    public function nombre(): string
    {
        return 'Desactivar cuenta bancaria';
    }

    public function campos(): array
    {
        return [$this->campoCuenta(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->cuentas->desactivar($datos['cuenta_id'], $datos['version']);

        return null;
    }
}
