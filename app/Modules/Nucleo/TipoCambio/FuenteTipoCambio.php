<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\TipoCambio;

use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\FuenteTipoCambioNoDisponible;

/**
 * De dónde se trae el tipo de cambio automáticamente (ADR 0012: SUNAT). Otra fuente es otra
 * implementación de esta interfaz y su caso en OrigenTipoCambio.
 */
interface FuenteTipoCambio
{
    public function origen(): OrigenTipoCambio;

    /**
     * El TC vigente que publica la fuente.
     *
     * @throws FuenteTipoCambioNoDisponible
     */
    public function vigente(): LecturaTipoCambio;
}
