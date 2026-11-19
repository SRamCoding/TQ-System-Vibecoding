<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Idioma de un saludo o una frase (plan 1.4b, D1b). Los cinco son para todos (plan 1.4aa, D6): el español domina y los
 * demás salen de vez en cuando (`SaludoPorReglas`, regla 1).
 */
enum IdiomaSaludo: string
{
    case Espanol = 'es';
    case Ingles = 'en';
    case Frances = 'fr';
    case Portugues = 'pt';
    case Aleman = 'de';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Espanol => 'Español',
            self::Ingles => 'Inglés',
            self::Frances => 'Francés',
            self::Portugues => 'Portugués',
            self::Aleman => 'Alemán',
        };
    }
}
