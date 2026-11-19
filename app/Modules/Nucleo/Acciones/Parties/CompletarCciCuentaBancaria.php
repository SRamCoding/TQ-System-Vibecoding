<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Completa el CCI de una cuenta que no lo tenía. Uno ya registrado no cambia (D5). */
final class CompletarCciCuentaBancaria extends AccionCuentaBancaria
{
    protected function operacion(): string
    {
        return 'completar-cci';
    }

    public function nombre(): string
    {
        return 'Completar CCI';
    }

    public function campos(): array
    {
        return [$this->campoCuenta(), Campo::texto('cci', 'CCI', maximo: 30), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->cuentas->completarCci($datos['cuenta_id'], $datos['cci'], $datos['version']);

        return null;
    }
}
