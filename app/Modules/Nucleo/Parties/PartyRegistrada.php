<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Resultado de registrar una party. `pareja` es la party con el DNI o el RUC 10 de la misma persona, si
 * existe: un aviso, no un bloqueo (plan 1.3, D9).
 */
final readonly class PartyRegistrada
{
    public function __construct(
        public int $id,
        public ?ResumenParty $pareja = null,
    ) {}
}
