<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

use Carbon\CarbonImmutable;

/**
 * Estación del hemisferio sur, para la ambientación de los días sin fecha especial (plan 1.4b, D5). Fechas fijas
 * aproximadas: verano desde el 21 de diciembre, otoño desde el 20 de marzo, invierno desde el 21 de junio y
 * primavera desde el 22 de septiembre.
 */
enum Temporada: string
{
    case Verano = 'verano';
    case Otono = 'otono';
    case Invierno = 'invierno';
    case Primavera = 'primavera';

    public static function deLaFecha(CarbonImmutable $fecha): self
    {
        $mesDia = $fecha->format('m-d');

        return match (true) {
            $mesDia >= '12-21' || $mesDia < '03-20' => self::Verano,
            $mesDia < '06-21' => self::Otono,
            $mesDia < '09-22' => self::Invierno,
            default => self::Primavera,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Verano => 'Verano',
            self::Otono => 'Otoño',
            self::Invierno => 'Invierno',
            self::Primavera => 'Primavera',
        };
    }
}
