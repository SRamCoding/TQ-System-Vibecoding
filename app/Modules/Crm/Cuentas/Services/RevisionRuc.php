<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Enums\CasoRuc;
use App\Modules\Nucleo\Parties\ResumenParty;

/** Lo que dice el primer paso de «Nueva cuenta» de un RUC ya validado (plan 2.20, D2). */
final readonly class RevisionRuc
{
    public function __construct(
        public CasoRuc $caso,
        /** Normalizado, sin espacios ni guiones. */
        public string $ruc,
        /** RUC 10: persona natural (nombres y apellidos, no razón social). */
        public bool $esPersonaNatural,
        public ?ResumenParty $ficha,
        public ?ResumenCuenta $cuenta,
    ) {}
}
