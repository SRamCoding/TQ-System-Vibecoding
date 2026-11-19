<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Lo que la lista de insumos necesita de una partida con APU (plan 2.9). */
final readonly class PartidaParaLista
{
    /**
     * @param  list<LineaParaLista>  $lineas
     */
    public function __construct(
        public string $codigo,
        public string $descripcion,
        public string $metrado,
        public string $unidad,
        public string $parcial,
        public array $lineas,
    ) {}
}
