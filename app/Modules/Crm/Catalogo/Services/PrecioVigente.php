<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

/** MVP rápido (t1-catalogo): el precio referencial vigente de un ítem, sin IGV, en soles. */
final readonly class PrecioVigente
{
    public function __construct(
        public string $precio,
        public ?string $costo,
        public ?string $margenObjetivo,
    ) {}
}
