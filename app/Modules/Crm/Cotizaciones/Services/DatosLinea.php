<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/**
 * Una línea del catálogo (`productoId`) o libre (`descripcion` y `unidadMedidaId`), plan 2.22, D6. Precio y costo
 * unitarios sin IGV; el costo lo escribe Preventa (el catálogo no trae costo). La del presupuesto va por
 * `CotizacionesService::enlazarPresupuesto`.
 */
final readonly class DatosLinea
{
    public function __construct(
        public string $cantidad,
        public string $precioUnitario,
        public string $costoUnitario,
        public string $descuentoPct = '0',
        public ?int $productoId = null,
        public ?string $descripcion = null,
        public ?int $unidadMedidaId = null,
    ) {}
}
