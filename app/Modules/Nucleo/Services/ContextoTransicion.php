<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use Closure;

/**
 * Dice si quien guarda el estado de un documento es TransicionesService: solo ahí cambia `estado_id` y solo así nace
 * un documento con estados, para que todo cambio quede en el historial (plan 1.5, D5). Se cierra aunque falle.
 */
final class ContextoTransicion
{
    private int $abiertos = 0;

    /**
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    public function durante(Closure $tarea): mixed
    {
        $this->abiertos++;

        try {
            return $tarea();
        } finally {
            $this->abiertos--;
        }
    }

    public function activo(): bool
    {
        return $this->abiertos > 0;
    }
}
