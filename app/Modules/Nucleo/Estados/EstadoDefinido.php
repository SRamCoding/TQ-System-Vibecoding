<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Enums\TonoEstado;

/**
 * Un estado en la definición de un documento (plan 1.5, D1). Nombre y tono son los iniciales: el sincronizador los
 * escribe al crear el estado y no los vuelve a pisar, porque se editan por pantalla.
 */
final readonly class EstadoDefinido
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public TonoEstado $tono,
        public bool $esInicial = false,
        public bool $esFinal = false,
        public bool $esEditable = false,
        public bool $esCancelacion = false,
    ) {}
}
