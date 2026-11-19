<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/** La versión de fábrica de ContratosDeOportunidad mientras Contratos no exista: «no hay contrato» (ADR 0020, regla 3). */
final class ContratosSinModulo implements ContratosDeOportunidad
{
    public function anularBorrador(int $oportunidadId, string $motivo): void {}

    public function etiquetasDeContratos(array $oportunidadIds): array
    {
        return [];
    }

    public function tieneDatos(): bool
    {
        return false;
    }
}
