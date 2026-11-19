<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Lo que una partida aporta al ajuste por redondeo (plan 2.9, D5): su parcial (metrado × P.U.) menos la suma de lo que
 * aportan sus insumos. Por ejemplo, «02.01 Cableado, 12.345 m, S/ 0.01».
 */
final readonly class AjusteDePartida
{
    public function __construct(
        public string $codigo,
        public string $descripcion,
        public string $metrado,
        public string $unidad,
        public string $monto,
    ) {}
}
