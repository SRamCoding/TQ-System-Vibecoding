<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * MVP rápido (t1-agenda): la próxima acción de cada oportunidad, que pone CRM-05 (Actividades). Sin ese módulo, no hay
 * próxima acción y la faja no la muestra (D5).
 */
interface ProximaAccionDeOportunidades
{
    public function existe(): bool;

    /**
     * @param  list<int>  $oportunidadIds
     * @return array<int, array{texto: string, vencida: bool}>
     */
    public function deLote(array $oportunidadIds): array;
}
