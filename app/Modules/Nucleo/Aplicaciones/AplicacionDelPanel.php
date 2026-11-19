<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aplicaciones;

use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;

/**
 * Una aplicación como la muestra el panel (plan 1.4p): en el muelle o en la cuadrícula. `id` es el de su tabla, según
 * `origen`; `version` solo la tiene una propia (se edita). `imagen`, el ícono subido por administración como `data:`
 * (plan 1.4z, D9); sin él, el de la lista.
 */
final readonly class AplicacionDelPanel
{
    public function __construct(
        public OrigenAplicacion $origen,
        public int $id,
        public string $nombre,
        public string $enlace,
        public IconoAplicacion $icono,
        public int $usos,
        public ?int $version = null,
        public ?string $imagen = null,
        // Plan 1.4z, D6: su programa de escritorio, si lo tiene, y si esta persona la abre ahí.
        public ?ProgramaEscritorio $escritorio = null,
        public bool $enEscritorio = false,
    ) {}

    /** Adónde lleva el clic: el programa, si la persona lo eligió y lo hay; si no, la web. */
    public function destino(): string
    {
        return $this->enEscritorio && $this->escritorio !== null ? $this->escritorio->enlace() : $this->enlace;
    }
}
