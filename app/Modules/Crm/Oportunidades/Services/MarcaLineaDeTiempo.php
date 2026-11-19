<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * Una gema de la línea de tiempo (plan 2.21c, D9): la oportunidad abierta (con su etapa, monto en soles y cierre
 * estimado) y si su cierre estimado ya pasó (va en ámbar, a la izquierda de hoy).
 */
final readonly class MarcaLineaDeTiempo
{
    public function __construct(
        public FilaFaja $oportunidad,
        public bool $vencida,
    ) {}
}
