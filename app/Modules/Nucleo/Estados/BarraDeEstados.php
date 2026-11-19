<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use LogicException;

/**
 * Lo que la barra de estados de un documento muestra a una persona (plan 1.5, D10): los estados en orden con el
 * actual marcado, las salidas que esa persona puede usar ahora y las que esperan aprobación. El componente de
 * pantalla llega con el primer documento real; el mensaje de edición bloqueada usa las mismas salidas.
 */
final readonly class BarraDeEstados
{
    /**
     * @param  list<EstadoEnBarra>  $estados
     * @param  list<SalidaDeEstado>  $salidas
     * @param  list<string>  $esperanAprobacion  nombres de las transiciones que salen del estado actual por aprobación
     */
    public function __construct(
        public array $estados,
        public array $salidas,
        public array $esperanAprobacion,
    ) {}

    public function actual(): EstadoEnBarra
    {
        foreach ($this->estados as $estado) {
            if ($estado->esActual) {
                return $estado;
            }
        }

        throw new LogicException('La barra no tiene estado actual.');
    }
}
