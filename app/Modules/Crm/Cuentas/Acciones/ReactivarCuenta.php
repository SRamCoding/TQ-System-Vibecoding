<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Reactiva una cuenta (plan 2.20, D9), con el mismo permiso que desactivarla. Exige su ficha activa. */
final class ReactivarCuenta extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.reactivar';
    }

    public function nombre(): string
    {
        return 'Reactivar cuenta';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.desactivar';
    }

    public function ejecutar(array $datos, User $usuario): ResumenCuenta
    {
        return app(CuentasService::class)->reactivar($datos['cuenta_id'], $datos['version']);
    }
}
