<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use Carbon\CarbonImmutable;

/** MVP rápido (t1-puente-erp, MV-006): lo que pide «Ganada» para que nazca el proyecto. */
final readonly class DatosDelProyectoAlGanar
{
    public function __construct(
        public int $jefeProyectoUserId,
        public CarbonImmutable $fechaInicio,
        public CarbonImmutable $fechaFinPrevista,
    ) {}
}
