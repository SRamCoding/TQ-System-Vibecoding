<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\CuentasBancariasService;

/**
 * Base de las acciones sobre las cuentas bancarias de una party (plan 1.3b, D5). Clave `nucleo.parties.<operacion>`,
 * permiso `parties.gestionar-cuentas-bancarias`. Las reglas (y la protección contra fraude) están en
 * CuentasBancariasService. Mueven dinero: siempre las ejecuta una persona (regla 9).
 */
abstract class AccionCuentaBancaria extends Accion
{
    public function __construct(protected readonly CuentasBancariasService $cuentas) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.parties.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'parties.gestionar-cuentas-bancarias';
    }

    protected function campoCuenta(): Campo
    {
        return Campo::entero('cuenta_id', 'Cuenta bancaria')->conReglas('exists:party_cuentas_bancarias,id');
    }

    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }
}
