<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** La hace la principal de su moneda; la que lo era deja de serlo. Nunca la de detracciones (D2). */
final class MarcarCuentaPrincipal extends AccionCuentaBancaria
{
    protected function operacion(): string
    {
        return 'marcar-cuenta-principal';
    }

    public function nombre(): string
    {
        return 'Marcar como cuenta principal';
    }

    public function campos(): array
    {
        return [$this->campoCuenta(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->cuentas->marcarPrincipal($datos['cuenta_id'], $datos['version']);

        return null;
    }
}
