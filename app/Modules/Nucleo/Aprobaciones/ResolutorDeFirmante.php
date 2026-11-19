<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\User;

/**
 * Quién firma un paso ahora mismo (plan 1.7, D1). Es una pieza aparte para que la delegación y los suplentes (D7,
 * después) se sumen sin tocar el servicio.
 */
interface ResolutorDeFirmante
{
    /**
     * Quien debe firmar: el que ocupa la jefatura, subiendo si está vacante, y sin contar a quien pidió la aprobación
     * (D6). Nulo si nadie puede.
     */
    public function firmante(FirmaAprobacion $firma, RondaAprobacion $ronda): ?User;

    /** Quien ocupa la jefatura (subiendo solo por vacantes), aunque sea quien pidió: el que podría autoaprobar. */
    public function titular(FirmaAprobacion $firma, RondaAprobacion $ronda): ?User;

    /** Cómo se nombra el paso en los mensajes: «la jefatura de Logística». */
    public function descripcion(FirmaAprobacion $firma, RondaAprobacion $ronda): string;
}
