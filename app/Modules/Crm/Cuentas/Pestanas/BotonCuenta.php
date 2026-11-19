<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Pestanas;

use Filament\Actions\Action;
use InvalidArgumentException;

/**
 * Un botón que otro módulo suma a la cabecera de la ficha de la cuenta (plan 2.20, D12): «Nueva oportunidad»,
 * «Registrar actividad». La fábrica arma la acción de Filament para una cuenta; la acción ejecuta un servicio de su
 * propio módulo, con su permiso.
 */
final readonly class BotonCuenta
{
    /**
     * @param  \Closure(int): Action  $fabrica  recibe el id de la cuenta
     */
    public function __construct(
        /** Minúsculas, cifras y guiones; es también el nombre de la acción de Filament. */
        public string $clave,
        public ?string $permiso,
        public \Closure $fabrica,
        public int $orden = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave del botón [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }
    }

    public function accion(int $cuentaId): Action
    {
        return ($this->fabrica)($cuentaId)->name($this->clave);
    }
}
