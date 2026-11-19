<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Las fechas especiales del saludo y la ambientación (plan 1.4b, D5). Sin Halloween ni santos. Cuándo cae cada una
 * lo calcula el calendario festivo; aquí, solo cuáles hay.
 */
enum Ocasion: string
{
    case AnioNuevo = 'anio_nuevo';
    case SemanaSanta = 'semana_santa';
    case DiaMadre = 'dia_madre';
    case DiaPadre = 'dia_padre';
    case FiestasPatrias = 'fiestas_patrias';
    case Navidad = 'navidad';
    case Cumpleanos = 'cumpleanos';
    case Aniversario = 'aniversario';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AnioNuevo => 'Año Nuevo',
            self::SemanaSanta => 'Semana Santa',
            self::DiaMadre => 'Día de la Madre',
            self::DiaPadre => 'Día del Padre',
            self::FiestasPatrias => 'Fiestas Patrias',
            self::Navidad => 'Navidad',
            self::Cumpleanos => 'Cumpleaños',
            self::Aniversario => 'Aniversario de Techquk',
        };
    }
}
