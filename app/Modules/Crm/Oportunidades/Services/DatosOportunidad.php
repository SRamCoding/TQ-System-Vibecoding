<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use Carbon\CarbonImmutable;

/** Lo que se escribe al crear o editar una oportunidad (plan 2.21, D7, D8, D9). */
final readonly class DatosOportunidad
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        /** Texto decimal. */
        public string $montoEstimado,
        public int $monedaId,
        public CarbonImmutable $fechaCierreEstimada,
        public int $lineaSolucionId,
        /** Nulo al crear: el de la cuenta (D7). */
        public ?int $origenComercialId = null,
        /** Nulo al crear: el ejecutivo de la cuenta (D5). */
        public ?int $ejecutivoUserId = null,
        public bool $necesidadConfirmada = false,
        public bool $presupuestoConfirmado = false,
        public ?int $decisorContactoId = null,
        /** Plan 2.0, D5: cuál es, con el origen «Otro». */
        public ?string $origenDetalle = null,
    ) {}
}
