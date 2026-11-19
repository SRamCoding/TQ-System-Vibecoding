<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo\Piezas;

/**
 * Una pieza de un dibujo de la fecha (plan 1.4e2): un fondo, un motivo o un acompañante, dibujado en el lienzo común
 * de 80 × 80 (`resources/views/filament/ambientacion/piezas/<tema>/`). Sus etiquetas dicen qué es (`flor`, `arbol`) y
 * `requiere`, qué tiene que haber en el dibujo para que tenga sentido (una abeja necesita una flor).
 */
final readonly class PiezaDibujo
{
    /**
     * @param  list<string>  $etiquetas
     * @param  list<string>  $requiere  todas tienen que estar en las etiquetas de las otras piezas
     */
    public function __construct(
        public TipoPiezaDibujo $tipo,
        public string $clave,
        public string $nombre,
        public string $vista,
        public array $etiquetas = [],
        public array $requiere = [],
    ) {}
}
