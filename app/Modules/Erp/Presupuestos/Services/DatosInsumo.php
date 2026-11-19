<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Lo que se escribe de un insumo del catálogo (plan 2.9, D8). El tipo no cambia después de crearlo. */
final readonly class DatosInsumo
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public string $tipo,
        public int $unidadMedidaId,
        public ?string $precioReferencia = null,
        public ?int $productoId = null,
        public ?int $categoriaCostoHoraId = null,
    ) {}
}
