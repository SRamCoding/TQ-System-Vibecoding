<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scope de búsqueda sin mayúsculas ni acentos (plan 1.3, D11). La regla está en BusquedaSinAcentos.
 *
 * @mixin Model
 */
trait BuscaSinAcentos
{
    /**
     * @param  Builder<static>  $consulta
     * @param  list<string>  $columnas  nombres fijos del código, nunca lo que llega del usuario
     */
    public function scopeBuscarTexto(Builder $consulta, ?string $texto, array $columnas): void
    {
        BusquedaSinAcentos::aplicar($consulta, $texto, $columnas);
    }
}
