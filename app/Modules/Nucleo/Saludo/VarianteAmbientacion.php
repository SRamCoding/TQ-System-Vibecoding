<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

/**
 * Un estilo de ambientación de una fecha o temporada (plan 1.4b, D8): su dibujo (una vista con el SVG y su CSS) y los
 * colores de la franja. Los colores viven aquí y no en el proveedor del panel: es la excepción anotada en
 * docs/convenciones/interfaz.md.
 */
final readonly class VarianteAmbientacion
{
    /**
     * @param  string  $tema  la ocasión o la temporada (`navidad`, `primavera`…)
     * @param  list<string>  $colores  de la franja, de izquierda a derecha (códigos `#RRGGBB`)
     * @param  string  $vista  dibuja el SVG; recibe `$animado`
     */
    public function __construct(
        public string $tema,
        public string $clave,
        public string $nombre,
        public array $colores,
        public string $vista,
        /** @var array<string, mixed> lo que necesita su vista; en un dibujo por piezas, la combinación, la luz y el clima (plan 1.4e2) */
        public array $datos = [],
    ) {}
}
