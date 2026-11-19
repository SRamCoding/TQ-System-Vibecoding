<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * El texto por página de un PDF, para verificar la evidencia de una sugerencia (plan 1.11, D6; ADR 0019). La librería
 * que lo lee vive detrás de esta interfaz: cambiarla toca una sola implementación.
 */
interface TextoDeAdjunto
{
    /**
     * El texto de cada página, en orden (la página 1 es la primera); nulo si el PDF no se puede leer.
     *
     * @return list<string>|null
     */
    public function paginas(string $pdf): ?array;
}
