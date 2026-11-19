<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Si una party es una persona o una organización (`parties.tipo`, con su CHECK en la base). Casi siempre lo
 * dice el documento (plan 1.3, D4); no se edita después de crearla (D5).
 */
enum TipoPersona: string
{
    case Natural = 'natural';
    case Juridica = 'juridica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Natural => 'Persona natural',
            self::Juridica => 'Persona jurídica',
        };
    }
}
