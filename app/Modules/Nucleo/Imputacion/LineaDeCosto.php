<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

use Illuminate\Database\Eloquent\Model;

/**
 * Una línea de un documento con costo, como la ve la revisión del exceso (plan 2.2, D4): su origen (la que tiene la
 * imputación) y su monto en la moneda de la línea. La partida y el tipo de cambio salen de su imputación.
 */
final readonly class LineaDeCosto
{
    /**
     * @param  string  $monto  decimal como texto, en la moneda de la línea
     */
    public function __construct(
        public Model $origen,
        public string $monto,
    ) {}
}
