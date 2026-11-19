<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Lo que la lista de insumos necesita de una línea del APU (plan 2.9). */
final readonly class LineaParaLista
{
    public function __construct(
        public int $insumoId,
        public string $codigo,
        public string $nombre,
        public string $tipo,
        public string $unidad,
        public bool $esSinDetallar,
        public string $cantidad,
        public string $parcialApu,
    ) {}
}
