<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Plantillas;

use Carbon\CarbonImmutable;

/** Una plantilla vista desde un módulo o una pantalla (ADR 0022): sin su contenido, que solo sale al aplicarla. */
final readonly class ResumenPlantilla
{
    public function __construct(
        public int $id,
        public string $tipo,
        public string $nombre,
        public ?string $descripcion,
        public bool $esDeLaEmpresa,
        public bool $esPropia,
        /** Lo que la persona puede hacer con ella: editar, actualizar desde un documento y desactivar. */
        public bool $puedeCambiar,
        public int $numeroVigente,
        public CarbonImmutable $vigenteDesde,
        /** Bloqueo optimista: la versión de la ficha leída al abrir. */
        public int $version,
    ) {}
}
