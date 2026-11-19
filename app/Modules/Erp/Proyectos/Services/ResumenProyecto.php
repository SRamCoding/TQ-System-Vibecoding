<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

/** Un proyecto visto desde otro módulo (C2, ADR 0020): de solo lectura, nunca el modelo. */
final readonly class ResumenProyecto
{
    public function __construct(
        public int $id,
        public string $codigo,
        public string $nombre,
        public int $clientePartyId,
        public string $clienteNombre,
        public ?int $contratoId,
        public int $jefeProyectoUserId,
        public string $estado,
        public string $estadoNombre,
        public bool $esInterno,
        public bool $aceptaCostos,
    ) {}
}
