<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** De dónde viene una aplicación del panel (plan 1.4p, D7): la puso administración o la sumó la persona. */
enum OrigenAplicacion: string
{
    case Techquk = 'techquk';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Techquk => 'De Techquk',
            self::Propia => 'Propia',
        };
    }
}
