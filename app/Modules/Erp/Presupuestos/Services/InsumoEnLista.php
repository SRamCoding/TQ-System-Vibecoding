<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Un insumo en la lista: cuánto lleva todo el presupuesto y cuánto cuesta (plan 2.9). */
final readonly class InsumoEnLista
{
    public function __construct(
        public int $insumoId,
        public string $codigo,
        public string $nombre,
        public string $tipo,
        public string $unidad,
        public string $cantidad,
        public string $monto,
    ) {}
}
