<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Una ficha cuyo nombre se parece al que se escribió (plan 2.20a, N1): para avisar antes de crear, nunca para
 * bloquear. `parecido` es `similarity()` de pg_trgm, de 0 a 1; el mismo nombre normalizado es 1.
 */
final readonly class CoincidenciaNombre
{
    public function __construct(
        public ResumenParty $party,
        public CampoNombre $campo,
        public bool $mismoNombre,
        public float $parecido,
    ) {}

    public function aviso(): string
    {
        return $this->campo->aviso($this->mismoNombre);
    }
}
