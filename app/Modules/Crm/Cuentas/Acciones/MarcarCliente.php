<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Marca a mano una cuenta como cliente desde una fecha (plan 2.20, D8.5): los clientes de antes del sistema. */
final class MarcarCliente extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.marcar-cliente';
    }

    public function nombre(): string
    {
        return 'Marcar como cliente';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::fecha('fecha', 'Cliente desde'),
            Campo::texto('comentario', 'Por qué es cliente', maximo: 1000),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.marcar-cliente';
    }

    public function ejecutar(array $datos, User $usuario): ResumenCuenta
    {
        return app(CuentasService::class)->marcarClienteManual($datos['cuenta_id'], $datos['version'], $datos['fecha'], $datos['comentario']);
    }
}
