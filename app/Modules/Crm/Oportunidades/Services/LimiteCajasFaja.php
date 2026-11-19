<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\ModoCajasFaja;
use InvalidArgumentException;

/**
 * El límite de cajas por lugar de la faja (plan 2.21b, D8; pedido B6), en un solo lugar: hasta 6, caja completa; de 7 a
 * 12, solo el código; más de 12, 11 cajas con su código y una pila «+N más». Vale igual en las zonas y en las salas.
 * Cuáles se dibujan lo dice `LugarFaja::ordenDeLasCajas()`.
 */
final class LimiteCajasFaja
{
    public const int HASTA_COMPLETAS = 6;

    public const int HASTA_CODIGO = 12;

    /** Las cajas que se dibujan cuando hay pila: una menos que HASTA_CODIGO, para que la pila ocupe el último lugar. */
    public const int DIBUJADAS_CON_PILA = 11;

    public static function para(int $cantidad): CajasFaja
    {
        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad de cajas no puede ser negativa.');
        }

        return match (true) {
            $cantidad <= self::HASTA_COMPLETAS => new CajasFaja(ModoCajasFaja::Completas, $cantidad, 0),
            $cantidad <= self::HASTA_CODIGO => new CajasFaja(ModoCajasFaja::Codigo, $cantidad, 0),
            default => new CajasFaja(ModoCajasFaja::CodigoConPila, self::DIBUJADAS_CON_PILA, $cantidad - self::DIBUJADAS_CON_PILA),
        };
    }
}
