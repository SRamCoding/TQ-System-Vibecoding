<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Lo que los demás módulos reciben de una party: su id para guardarlo y su etiqueta para mostrarla
 * («razón social · número de documento»). Nunca el modelo (regla 2).
 */
final readonly class ResumenParty
{
    public function __construct(
        public int $id,
        public string $etiqueta,
        public bool $activa,
    ) {}
}
