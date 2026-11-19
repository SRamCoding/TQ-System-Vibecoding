<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Illuminate\Database\Eloquent\Model;

/**
 * Lo que un cambio de estado hace fuera de la fila del documento: comprometer, mover stock, crear otro documento
 * (plan 1.5, D3). Corre dentro de la transacción del cambio; si falla, no cambia nada. Los dos métodos son
 * obligatorios: uno que no se deshace lo dice en `revertir()`, no lo omite.
 */
interface Efecto
{
    /**
     * Hace el efecto y devuelve lo que hizo (ids, montos como texto decimal, partidas). Se guarda en
     * `efectos_aplicados.datos` y es lo que recibe `revertir()`.
     *
     * @return array<string, mixed>
     */
    public function aplicar(Model $documento): array;

    /**
     * Deshace lo que hizo, con los datos que guardó al aplicarse: no recalcula con los de hoy.
     *
     * @param  array<string, mixed>  $datos
     */
    public function revertir(Model $documento, array $datos): void;
}
