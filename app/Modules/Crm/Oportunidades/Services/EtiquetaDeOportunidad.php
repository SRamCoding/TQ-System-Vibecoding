<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;

/** Cómo se nombra una oportunidad en el historial de la cuenta (plan 2.21, D13.3): «OP-2026-0012 · Nombre». */
final class EtiquetaDeOportunidad implements OportunidadEnlazable
{
    public function etiqueta(int $oportunidadId): string
    {
        $oportunidad = Oportunidad::query()->select(['numero', 'nombre'])->find($oportunidadId);

        return $oportunidad === null ? "Oportunidad #{$oportunidadId}" : "{$oportunidad->numero} · {$oportunidad->nombre}";
    }
}
