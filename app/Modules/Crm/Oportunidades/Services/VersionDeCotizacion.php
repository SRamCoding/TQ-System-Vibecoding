<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use Carbon\CarbonImmutable;

/**
 * Una versión de una cotización de la oportunidad, para su ficha (plan 2.21d, D10): el recorrido de los documentos y
 * «¿cómo se fue negociando?». La da Cotizaciones por el puerto `CotizacionDeOportunidad` (C3). El margen es dato de
 * costos: la ficha solo lo muestra con `campos.ver-costos` (ADR 0016).
 */
final readonly class VersionDeCotizacion
{
    public function __construct(
        public int $id,
        /** «COT-2026-0007 v2». */
        public string $identificador,
        public string $numero,
        public int $numeroVersion,
        public string $estado,
        public string $total,
        public string $moneda,
        public ?CarbonImmutable $fechaEmision,
        public ?string $margenPorcentaje,
        /** El mínimo con que se emitió: por debajo, «margen bajo» en Atención. */
        public ?string $margenMinimo,
        public bool $esAceptada,
        public ?int $presupuestoId,
        public string $url,
    ) {}
}
