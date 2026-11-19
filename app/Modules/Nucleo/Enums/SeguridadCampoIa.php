<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Qué tan seguro es un campo propuesto por la IA (plan 1.11, D6): alta, media o baja, nunca un porcentaje. Sale de
 * señales comprobables; lo que diga el modelo puede bajarla, nunca subirla.
 */
enum SeguridadCampoIa: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    /** La menor entre esta y `$tope`: lo que dice el modelo solo puede bajarla. */
    public function hasta(self $tope): self
    {
        return $this->nivel() <= $tope->nivel() ? $this : $tope;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Baja => 'Baja',
        };
    }

    private function nivel(): int
    {
        return match ($this) {
            self::Alta => 3,
            self::Media => 2,
            self::Baja => 1,
        };
    }
}
