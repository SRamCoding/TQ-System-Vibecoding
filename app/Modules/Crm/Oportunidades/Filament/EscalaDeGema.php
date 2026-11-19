<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use InvalidArgumentException;

/**
 * El tamaño de una gema según su monto (plan 2.21c, D3, propuesta del agente): crece con la raíz del monto en moneda
 * local hasta `MONTO_TOPE`, entre un mínimo y un máximo en píxeles, para que una oportunidad enorme no rompa la tarjeta.
 * Sin monto (sin T.C. dentro de la tolerancia), el mínimo. Es una medida de pantalla, no dinero: el cociente ya puede ser
 * un número de coma flotante.
 */
final class EscalaDeGema
{
    /** Desde este monto en soles, la gema tiene el tamaño máximo. */
    public const string MONTO_TOPE = '500000';

    /** Los tamaños de la tarjeta del tablero (el prototipo: de 44 a 56). */
    public const int MINIMO = 44;

    public const int MAXIMO = 56;

    public static function tamano(?string $montoEnSoles, int $minimo = self::MINIMO, int $maximo = self::MAXIMO): int
    {
        if ($minimo < 1 || $maximo < $minimo) {
            throw new InvalidArgumentException("Tamaños de gema no válidos: de {$minimo} a {$maximo}.");
        }

        if ($montoEnSoles === null || bccomp($montoEnSoles, '0', 2) <= 0) {
            return $minimo;
        }

        $proporcion = bccomp($montoEnSoles, self::MONTO_TOPE, 2) >= 0 ? 1.0 : (float) bcdiv($montoEnSoles, self::MONTO_TOPE, 6);

        return (int) round($minimo + ($maximo - $minimo) * sqrt($proporcion));
    }
}
