<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;

/** Edita una cuenta que atiendo (plan 2.20, D7, los ejecutivos). Los mismos campos que EditarCuenta. */
final class EditarCuentaPropia extends EditarCuenta
{
    public function clave(): string
    {
        return 'crm.cuentas.editar-propias';
    }

    public function nombre(): string
    {
        return 'Editar una cuenta que atiendo';
    }

    public function permiso(): string
    {
        return CuentasService::PERMISO_EDITAR_PROPIAS;
    }
}
