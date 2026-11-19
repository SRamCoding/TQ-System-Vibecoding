<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Organigrama;

use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;

/**
 * Lo que se escribe de una unidad al crearla o editarla (plan 1.4, D5). La jefatura se designa aparte: su puesto
 * tiene que existir antes, dentro de la unidad.
 */
final readonly class DatosUnidad
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public TipoUnidadOrganizativa $tipo,
        public ?int $superiorId = null,
        public int $orden = 0,
    ) {}
}
