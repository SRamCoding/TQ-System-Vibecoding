<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Las dos partes del saludo (plan 1.4b, D1), el saludo con el nombre y la frase de debajo, y el agradecimiento del
 * aniversario de la barra superior (D10) (`frases_saludo.tipo`).
 */
enum TipoFraseSaludo: string
{
    case Saludo = 'saludo';
    case Frase = 'frase';
    /** Plan 1.4b, D10: en la barra superior, en los días del aniversario. Lleva {anios}. */
    case Agradecimiento = 'agradecimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Saludo => 'Saludo (con {nombre})',
            self::Frase => 'Frase',
            self::Agradecimiento => 'Agradecimiento del aniversario (con {anios}, hasta 60 letras)',
        };
    }
}
