<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/** Una red social como la muestra una pantalla: de qué red es, su ícono y el enlace (plan 1.4o). */
final readonly class ResumenRedSocial
{
    public function __construct(
        public int $id,
        public ?int $contactoId,
        public int $redSocialId,
        public string $red,
        public string $icono,
        public string $enlace,
        public bool $esPrivada,
        public int $version,
    ) {}
}
