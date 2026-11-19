<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aplicaciones;

use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;

/** Lo que se escribe al agregar o cambiar una aplicación propia (plan 1.4p, D7). */
final readonly class DatosAplicacionPropia
{
    public function __construct(
        public string $nombre,
        public string $enlace,
        public IconoAplicacion $icono = IconoAplicacion::Generica,
        // Plan 1.4z, D6: su programa de escritorio, de la lista cerrada; nulo, solo web.
        public ?ProgramaEscritorio $escritorio = null,
    ) {}
}
