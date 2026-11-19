<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;

/** Requisito propio para calificar (plan 2.21, D9: diseño del 13 oct y panel «Avanzar» del v4). */
final readonly class DecisorIdentificado implements RequisitoDeEtapa
{
    public function exigidoAlEntrarEn(): string
    {
        return 'calificada';
    }

    public function texto(): string
    {
        return 'Decisor identificado';
    }

    public function cumple(int $oportunidadId): bool
    {
        return Oportunidad::query()->whereKey($oportunidadId)->first()?->decisor_contacto_id !== null;
    }
}
