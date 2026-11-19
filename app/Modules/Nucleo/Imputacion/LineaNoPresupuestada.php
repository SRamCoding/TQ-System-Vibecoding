<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/**
 * Una línea de un documento con costo que va a la PC-99: un gasto no presupuestado (plan 2.6, D1). La arma
 * `ExcesoDePartidaService` al enviar y al aprobar, para que Presupuestos avise (`FuenteDeDestinos`, D6). Solo números
 * y ids: sin modelos de otro módulo.
 */
final readonly class LineaNoPresupuestada
{
    public function __construct(
        public int $imputacionId,
        public int $partidaId,
        public int $proyectoId,
        public ?int $motivoId,
        /** En soles, con el tipo de cambio de la línea, al céntimo. */
        public string $montoSoles,
    ) {}
}
