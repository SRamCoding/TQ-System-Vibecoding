<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use Carbon\CarbonImmutable;

/**
 * Lo que se pide para crear un proyecto (C2, plan 2.1, D4). En uno interno, el cliente es Techquk y se ignora
 * `clientePartyId`; en uno que nace de un contrato, es el cliente del contrato. La moneda, por código: en la etapa 2,
 * solo soles (D7).
 */
final readonly class DatosProyecto
{
    public function __construct(
        public string $nombre,
        public int $jefeProyectoUserId,
        public CarbonImmutable $fechaInicio,
        public CarbonImmutable $fechaFinPrevista,
        public ?int $clientePartyId = null,
        public string $moneda = 'PEN',
    ) {}
}
