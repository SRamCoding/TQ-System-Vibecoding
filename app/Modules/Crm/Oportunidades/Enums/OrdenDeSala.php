<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

/**
 * Cuáles cuatro se ven en Ganadas y Perdidas del tablero de gemas, y en qué orden (plan 2.21c, D15). Por defecto las
 * más recientes; «Por motivo de pérdida», solo en Perdidas. Cada persona elige la suya y se recuerda
 * (`TableroDeGemas::vistaDeSala`).
 */
enum OrdenDeSala: string
{
    case Recientes = 'recientes';
    case Monto = 'monto';
    case Motivo = 'motivo';

    /**
     * Las que admite una sala, en el orden del selector.
     *
     * @return list<self>
     */
    public static function de(LugarFaja $sala): array
    {
        return $sala === LugarFaja::Perdidas ? self::cases() : [self::Recientes, self::Monto];
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Recientes => 'Más recientes',
            self::Monto => 'Mayor monto',
            self::Motivo => 'Por motivo de pérdida',
        };
    }

    /** El final de «Se ven 4 de N: …». */
    public function explicacion(): string
    {
        return match ($this) {
            self::Recientes => 'las más recientes',
            self::Monto => 'las de mayor monto',
            self::Motivo => 'por motivo, el más frecuente primero',
        };
    }
}
