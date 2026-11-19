<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

/**
 * Una versión de la cotización para otros módulos (C3: Contratos; plan 2.22, D12): sus montos (texto decimal), sus
 * condiciones ya escritas como texto y sus líneas. Sin costo ni margen: son datos sensibles (D15).
 */
final readonly class ResumenCotizacion
{
    /**
     * @param  list<array{descripcion: string, unidad: string, cantidad: string, precio_unitario: string, descuento_porcentaje: string, subtotal: string}>  $lineas
     */
    public function __construct(
        public int $id,
        public string $numero,
        public int $numeroVersion,
        /** «COT-2026-0190 v1» */
        public string $identificador,
        public string $estado,
        public int $oportunidadId,
        public int $cuentaId,
        public string $moneda,
        public string $subtotal,
        public string $igv,
        public string $total,
        public ?string $fechaEmision,
        public ?string $fechaVencimiento,
        public bool $esAceptada,
        public ?string $plazo,
        public ?string $formaPago,
        public ?string $garantia,
        public ?string $lugarEntrega,
        public ?int $presupuestoId,
        public array $lineas,
    ) {}
}
