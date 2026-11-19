<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Models\IaPrecioModelo;
use Carbon\CarbonImmutable;

/**
 * Cuánto costó una llamada (plan 1.11, D3): al precio por millón de tokens vigente el día de la llamada (hora de Lima),
 * sin recalcular el pasado. Sin precio vigente, costo 0 y la llamada lo dice (`precio_id` nulo).
 */
final class CostoLlamadaIa
{
    public static function precioVigente(int $modeloId, CarbonImmutable $momento): ?IaPrecioModelo
    {
        return IaPrecioModelo::query()
            ->where('modelo_id', $modeloId)
            ->where('vigente_desde', '<=', $momento->setTimezone('America/Lima')->toDateString())
            ->orderByDesc('vigente_desde')
            ->first();
    }

    /** En dólares, con 6 decimales, redondeado a la mitad hacia arriba. */
    public static function costo(?IaPrecioModelo $precio, int $tokensEntrada, int $tokensSalida): string
    {
        if ($precio === null) {
            return '0.000000';
        }

        $total = bcadd(
            bcmul((string) $tokensEntrada, $precio->usd_millon_entrada, 12),
            bcmul((string) $tokensSalida, $precio->usd_millon_salida, 12),
            12,
        );

        // Por millón, con dos decimales de más para redondear.
        $exacto = bcdiv($total, '1000000', 8);

        return bcadd($exacto, '0.0000005', 6);
    }
}
