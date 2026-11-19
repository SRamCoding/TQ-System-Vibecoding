<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Plantillas;

/**
 * Lo que devuelve aplicar una plantilla (ADR 0022; plan 2.11, D3): la propuesta que armó su tipo con una copia del
 * contenido, y la versión de la que salió, que el documento guarda en `plantilla_version_id` al confirmarse.
 */
final readonly class PropuestaDesdePlantilla
{
    public function __construct(
        public int $plantillaVersionId,
        public string $nombre,
        public int $numero,
        public object $propuesta,
    ) {}
}
