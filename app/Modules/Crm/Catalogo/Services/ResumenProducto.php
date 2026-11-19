<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

/** Un producto del catálogo, de solo lectura (C4; plan 2.60, D2 y D4). */
final readonly class ResumenProducto
{
    public function __construct(
        public int $id,
        public string $codigo,
        /** La descripción corta si la hay; si no, la completa: para selectores y líneas (D2). */
        public string $nombre,
        /** La descripción completa: para la orden al proveedor y las especificaciones de una cotización (D2). */
        public string $descripcion,
        /** Llave de `unidades_medida` del núcleo, para guardarla en las líneas (D4). */
        public int $unidadMedidaId,
        /** Su abreviatura: «UND», «M». */
        public string $unidad,
        public bool $requiereSerie,
        public bool $esServicio,
        public bool $esActivo,
    ) {}
}
