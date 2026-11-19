<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/** Lo que dibuja una tarjeta del Resumen: su título, sus datos y sus avisos. La vista no sabe de qué módulo viene. */
final readonly class ContenidoTarjeta
{
    /**
     * @param  list<LineaResumen>  $lineas
     * @param  list<AvisoResumen>  $avisos
     */
    public function __construct(
        public string $titulo,
        public array $lineas = [],
        public array $avisos = [],
    ) {}
}
