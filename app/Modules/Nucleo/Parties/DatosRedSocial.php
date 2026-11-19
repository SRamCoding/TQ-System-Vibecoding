<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/** Lo que se escribe al agregar o cambiar una red social (plan 1.4o). */
final readonly class DatosRedSocial
{
    public function __construct(
        public int $redSocialId,
        public string $enlace,
        public bool $esPrivada = false,
        /** Plan 2.0, D5: con «Otra red», cuál es. */
        public ?string $detalle = null,
    ) {}
}
