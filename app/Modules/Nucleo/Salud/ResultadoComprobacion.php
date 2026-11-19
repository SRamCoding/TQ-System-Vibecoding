<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

/**
 * Lo que muestra un recuadro de la página de salud (plan 1.12, D2): su estado, una frase y algunas líneas de detalle.
 * Nunca datos de personas ni contenido de documentos (regla 10).
 */
final readonly class ResultadoComprobacion
{
    /** @param array<string, string> $detalle etiqueta => valor */
    public function __construct(
        public EstadoSalud $estado,
        public string $resumen,
        public array $detalle = [],
    ) {}
}
