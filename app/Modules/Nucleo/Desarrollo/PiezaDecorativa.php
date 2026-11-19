<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo;

/**
 * Una pieza decorativa que el modo desarrollo deja cambiar en el sitio (plan 1.4i, D3): ofrece todas sus variantes,
 * agrupadas por fecha o estación. La ambientación de pantalla completa (1.4d) y los dibujos por piezas (1.4e) se suman
 * como una pieza más en RegistroPiezasDecorativas.
 */
interface PiezaDecorativa
{
    /** Minúsculas y guiones; marca la pieza en la página (`data-tq-pieza`) y su elección en la sesión. */
    public function clave(): string;

    public function nombre(): string;

    /**
     * Grupo (fecha o estación) => [valor => nombre de la variante].
     *
     * @return array<string, array<string, string>>
     */
    public function opciones(): array;
}
