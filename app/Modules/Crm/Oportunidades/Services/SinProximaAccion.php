<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

final class SinProximaAccion implements ProximaAccionDeOportunidades
{
    public function existe(): bool
    {
        return false;
    }

    public function deLote(array $oportunidadIds): array
    {
        return [];
    }
}
