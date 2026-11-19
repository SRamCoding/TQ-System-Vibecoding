<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Pasa una o varias cuentas a otro ejecutivo (plan 2.20, D7), todo o nada. Desde la ficha de una cuenta se manda su
 * versión; desde la lista, varias sin versión. Devuelve cuántas cambiaron.
 */
final class ReasignarCuentas extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.reasignar';
    }

    public function nombre(): string
    {
        return 'Reasignar cuentas';
    }

    public function campos(): array
    {
        return [
            Campo::listaDeIds('cuenta_ids', 'Cuentas', 'cuentas', 'id'),
            Campo::entero('ejecutivo_user_id', 'Nuevo ejecutivo')->conReglas('min:1'),
            Campo::booleano('incluir_oportunidades_abiertas', 'Pasar también sus oportunidades abiertas'),
            Campo::entero('version', 'Versión')->opcional()->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.reasignar';
    }

    public function ejecutar(array $datos, User $usuario): int
    {
        return app(CuentasService::class)->reasignar($datos['cuenta_ids'], $datos['ejecutivo_user_id'], $datos['incluir_oportunidades_abiertas'], $datos['version'] ?? null);
    }
}
