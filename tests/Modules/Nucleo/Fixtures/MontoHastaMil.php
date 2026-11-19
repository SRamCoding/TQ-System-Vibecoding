<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Condición de ejemplo (plan 1.5, D6): el monto del documento no supera 1,000.00. */
final readonly class MontoHastaMil implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        return bccomp((string) $documento->getAttribute('monto'), '1000.00', 2) > 0
            ? "«{$transicion->nombre}» solo se hace con montos de hasta 1,000.00."
            : null;
    }
}
