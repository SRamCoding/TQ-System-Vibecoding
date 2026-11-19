<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Lo que se escribe de un insumo en un APU (plan 2.9, D4). Material y subcontrato: `cantidadNeta` y
 * `desperdicioPct`. Mano de obra y equipo: la `cuadrilla` (1 por defecto) y, solo si no usan el de la partida, su
 * `rendimiento` con su unidad (`hora` o `jornada`, D12). `precio` null: el precio inicial (sugerencia de C5 o precio de referencia, D8).
 */
final readonly class DatosLineaApu
{
    public function __construct(
        public int $insumoId,
        public ?string $cantidadNeta = null,
        public string $desperdicioPct = '0',
        public ?string $rendimiento = null,
        public ?string $unidadRendimiento = null,
        public string $cuadrilla = '1',
        public ?string $precio = null,
    ) {}
}
