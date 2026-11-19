<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;

/** Borra una cuenta que nunca se usó; si ya se usa, se desactiva. */
final class BorrarCuentaBancaria extends AccionCuentaBancaria
{
    protected function operacion(): string
    {
        return 'borrar-cuenta-bancaria';
    }

    public function nombre(): string
    {
        return 'Borrar cuenta bancaria';
    }

    public function campos(): array
    {
        return [$this->campoCuenta(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->cuentas->borrar($datos['cuenta_id'], $datos['version']);

        return null;
    }
}
