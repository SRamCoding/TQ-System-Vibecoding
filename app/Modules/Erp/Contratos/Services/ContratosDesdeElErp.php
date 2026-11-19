<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Services;

use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Erp\Contratos\Models\Contrato;

/** MVP rápido (t2-contratos): lo que el CRM pide de los contratos (la columna «Contrato» de las ganadas). */
final readonly class ContratosDesdeElErp implements ContratosDeOportunidad
{
    public function anularBorrador(int $oportunidadId, string $motivo): void
    {
        app(ContratosService::class)->anularDeOportunidad($oportunidadId);
    }

    public function etiquetasDeContratos(array $oportunidadIds): array
    {
        return Contrato::query()->whereIn('oportunidad_id', $oportunidadIds)->where('estado', '!=', 'anulado')->pluck('codigo', 'oportunidad_id')->all();
    }

    public function tieneDatos(): bool
    {
        return true;
    }
}
