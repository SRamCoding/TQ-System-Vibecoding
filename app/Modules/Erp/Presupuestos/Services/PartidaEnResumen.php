<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Una partida en el resumen de C8, a costo directo (plan 2.9, D7). */
final readonly class PartidaEnResumen
{
    public function __construct(
        public string $codigo,
        public string $tituloCodigo,
        public string $tituloNombre,
        public string $descripcion,
        public string $unidad,
        public string $metrado,
        public string $precioUnitario,
        public string $parcial,
    ) {}
}
