<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Enums\TonoEstado;

/** Un estado en la barra de un documento, en su orden (plan 1.5, D10). El tono va siempre con el nombre. */
final readonly class EstadoEnBarra
{
    public function __construct(
        public int $id,
        public string $codigo,
        public string $nombre,
        public TonoEstado $tono,
        public bool $esActual,
        public bool $esFinal,
        public bool $esCancelacion,
    ) {}
}
