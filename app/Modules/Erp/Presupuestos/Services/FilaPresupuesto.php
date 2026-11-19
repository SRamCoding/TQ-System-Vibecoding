<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Un título o una partida del presupuesto como los muestra la pestaña «Partidas» de su ficha (plan 2.10, D4), en el
 * orden de código. El título lleva la suma de los parciales de sus partidas (las de sus subtítulos incluidas).
 */
final readonly class FilaPresupuesto
{
    public function __construct(
        public bool $esTitulo,
        public int $id,
        public ?int $tituloId,
        public string $codigo,
        public string $descripcion,
        /** 0 en un título de la raíz; cada «.» del código, un nivel más. */
        public int $nivel,
        public ?string $unidad,
        public ?string $metrado,
        public ?string $precioUnitario,
        public string $parcial,
        public int $lineasApu,
        public int $version,
    ) {}
}
