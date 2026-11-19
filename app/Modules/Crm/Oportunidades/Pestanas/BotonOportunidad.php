<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Pestanas;

use Filament\Actions\Action;
use InvalidArgumentException;

/**
 * Un botón que otro módulo suma a la cabecera de la ficha de la oportunidad (plan 2.21, D12): «Nueva cotización»,
 * «Registrar actividad». La fábrica arma la acción de Filament para una oportunidad; la acción ejecuta un servicio de su
 * propio módulo, con su permiso.
 */
final readonly class BotonOportunidad
{
    /**
     * @param  \Closure(int): Action  $fabrica  recibe el id de la oportunidad
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

    public function accion(int $oportunidadId): Action
    {
        return ($this->fabrica)($oportunidadId)->name($this->clave);
    }
}
