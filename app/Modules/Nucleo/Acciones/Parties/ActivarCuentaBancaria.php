<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Vuelve a recibir pagos: también avisa por evento, como registrar una nueva. */
final class ActivarCuentaBancaria extends AccionCuentaBancaria
{
    protected function operacion(): string
    {
        return 'activar-cuenta-bancaria';
    }

    public function nombre(): string
    {
        return 'Activar cuenta bancaria';
    }

    public function campos(): array
    {
        return [$this->campoCuenta(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->cuentas->activar($datos['cuenta_id'], $datos['version']);

        return null;
    }
}
