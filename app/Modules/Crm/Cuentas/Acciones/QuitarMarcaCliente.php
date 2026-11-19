<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Quita la marca de cliente puesta a mano por error (plan 2.20, D8.6), con su motivo escrito. */
final class QuitarMarcaCliente extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.quitar-marca-cliente';
    }

    public function nombre(): string
    {
        return 'Quitar la marca de cliente';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::texto('motivo', 'Motivo', maximo: 1000),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.quitar-marca-cliente';
    }

    public function ejecutar(array $datos, User $usuario): ResumenCuenta
    {
        return app(CuentasService::class)->quitarMarcaCliente($datos['cuenta_id'], $datos['version'], $datos['motivo'], $usuario);
    }
}
