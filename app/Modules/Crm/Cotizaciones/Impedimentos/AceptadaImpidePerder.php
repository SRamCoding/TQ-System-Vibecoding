<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Impedimentos;

use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentoDeOportunidad;

/** Con una cotización aceptada, la oportunidad no se pierde: primero se anula la aceptación (plan 2.22, D13). */
final readonly class AceptadaImpidePerder implements ImpedimentoDeOportunidad
{
    public function transiciones(): array
    {
        return ['perder'];
    }

    public function impiden(int $oportunidadId): array
    {
        return AceptadaImpideAnular::de($oportunidadId);
    }
}
