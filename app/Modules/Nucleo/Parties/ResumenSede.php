<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/** Lo que otro módulo recibe de una sede: id y etiqueta («Almacén Chiclayo · Av. Principal 123»). */
final readonly class ResumenSede
{
    public function __construct(
        public int $id,
        public string $etiqueta,
        public ?string $codigoEstablecimiento,
    ) {}
}
