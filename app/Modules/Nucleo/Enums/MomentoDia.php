<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Momento del día en hora de Lima (plan 1.4b, D1): mañana de 5:00 a 11:59, tarde de 12:00 a 18:59, noche el resto.
 */
enum MomentoDia: string
{
    case Manana = 'manana';
    case Tarde = 'tarde';
    case Noche = 'noche';

    public static function deLaHora(int $hora): self
    {
        return match (true) {
            $hora >= 5 && $hora < 12 => self::Manana,
            $hora >= 12 && $hora < 19 => self::Tarde,
            default => self::Noche,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Manana => 'Mañana',
            self::Tarde => 'Tarde',
            self::Noche => 'Noche',
        };
    }
}
