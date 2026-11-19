<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\ModoCajasFaja;

/** Lo que se dibuja en un lugar de la faja (plan 2.21b, D8): el modo, cuántas cajas y cuántas quedan en la pila. */
final readonly class CajasFaja
{
    public function __construct(
        public ModoCajasFaja $modo,
        public int $dibujadas,
        /** Las que no se dibujan: la pila «+N más». 0 si no hay pila. */
        public int $enPila,
    ) {}
}
