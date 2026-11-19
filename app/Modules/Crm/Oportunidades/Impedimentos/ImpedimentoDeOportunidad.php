<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Impedimentos;

use App\Modules\Nucleo\Estados\DocumentoQueImpide;

/**
 * Algo de otro módulo que impide perder o retroceder una oportunidad (plan 2.21, D12.3). Anular usa el mecanismo del
 * núcleo (`RevisionAntesDeAnular` en `RegistroDocumentos`): esto es para las salidas que no son cancelaciones.
 */
interface ImpedimentoDeOportunidad
{
    /** @return list<string> los códigos de transición que revisa: `perder`, `retroceder` */
    public function transiciones(): array;

    /** @return list<DocumentoQueImpide> */
    public function impiden(int $oportunidadId): array;
}
