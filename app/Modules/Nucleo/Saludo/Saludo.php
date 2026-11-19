<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;

/**
 * Lo que se muestra arriba del inicio (plan 1.4b, D1): el saludo con el nombre ya puesto y, debajo, la frase con su
 * fuente. La ocasión o, si no hay, la temporada eligen la ambientación.
 */
final readonly class Saludo
{
    public function __construct(
        public string $saludo,
        public ?string $frase,
        public ?string $fuente,
        public IdiomaSaludo $idioma,
        public ?Ocasion $ocasion,
        public Temporada $temporada,
    ) {}
}
