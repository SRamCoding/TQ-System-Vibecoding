<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * Los totales del embudo en moneda local (plan 2.21, D8): lo que está en otra moneda se convierte al leer, al tipo de
 * compra (es dinero por cobrar), y lo que no tiene tipo de cambio dentro de la tolerancia va aparte, sin inventar tasa.
 * Montos como texto decimal.
 */
final readonly class EmbudoEnSoles
{
    /**
     * @param  array<string, array{monto: string, tipoCambio: string}>  $convertido  por moneda extranjera: lo sumado en
     *                                                                               su moneda y el T.C. usado
     * @param  array<string, array{monto: string, cantidad: int}>  $sinTipoCambio  por moneda: lo que quedó aparte
     */
    public function __construct(
        public int $cantidad,
        public string $total,
        public string $ponderado,
        public array $convertido,
        public array $sinTipoCambio,
    ) {}
}
