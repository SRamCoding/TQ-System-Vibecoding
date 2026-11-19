<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\Ocasion;
use InvalidArgumentException;

/**
 * Una versión del gran momento de una fecha (plan 1.4d, D7): su escena de 30 segundos (una vista con el SVG y su CSS)
 * y el título del diálogo. El año elige cuál se muestra; agregar una es una vista y una línea en el registro.
 */
final readonly class VarianteGranMomento
{
    /**
     * @param  string  $vista  dibuja la escena a pantalla completa; recibe `$animado`
     */
    public function __construct(
        public Ocasion $ocasion,
        public string $clave,
        public string $nombre,
        public string $titulo,
        public string $vista,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la variante [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }
    }
}
