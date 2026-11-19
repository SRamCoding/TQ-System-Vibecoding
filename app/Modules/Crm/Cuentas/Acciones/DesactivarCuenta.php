<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Services\CuentaDesactivada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Desactiva una cuenta con su motivo (plan 2.20, D9); nunca se borra. Con «desactivar también la ficha», para una
 * creada por error con el RUC equivocado.
 */
final class DesactivarCuenta extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.desactivar';
    }

    public function nombre(): string
    {
        return 'Desactivar cuenta';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::motivoDe('motivo_id', 'Motivo', CuentasServiceProvider::FAMILIA_DESACTIVAR, detalle: 'detalle'),
            Campo::texto('detalle', 'Detalle', maximo: 1000)->opcional(),
            Campo::booleano('desactivar_ficha', 'Desactivar también la ficha')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.desactivar';
    }

    public function ejecutar(array $datos, User $usuario): CuentaDesactivada
    {
        return app(CuentasService::class)->desactivar($datos['cuenta_id'], $datos['version'], $datos['motivo_id'], $datos['detalle'] ?? null, (bool) ($datos['desactivar_ficha'] ?? false), $usuario);
    }
}
