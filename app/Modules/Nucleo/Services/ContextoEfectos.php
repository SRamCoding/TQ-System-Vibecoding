<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Estados\EfectoExterno;
use Closure;

/**
 * Dice si lo que se está guardando lo hace un efecto de un cambio de estado (plan 1.5, D8, ajuste 1): solo ahí cambia
 * un campo derivado. El servicio de transiciones y los jobs de efectos externos corren cada efecto con `durante()`;
 * el contexto se cierra aunque el efecto falle.
 */
final class ContextoEfectos
{
    /** @var list<Efecto|EfectoExterno> */
    private array $efectos = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    public function durante(Efecto|EfectoExterno $efecto, Closure $tarea): mixed
    {
        $this->efectos[] = $efecto;

        try {
            return $tarea();
        } finally {
            array_pop($this->efectos);
        }
    }

    public function activo(): bool
    {
        return $this->efectos !== [];
    }
}
