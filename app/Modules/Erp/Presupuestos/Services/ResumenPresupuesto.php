<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Lo que C8 entrega de una versión del presupuesto (plan 2.9, D7). Montos y porcentajes como texto decimal (2
 * decimales). `sinDetallar`: cuánto del costo directo va con «Precio sin detallar» (D10).
 */
final readonly class ResumenPresupuesto
{
    /**
     * @param  list<PartidaEnResumen>  $partidas  por código
     */
    public function __construct(
        public int $id,
        public string $numero,
        public int $numeroVersion,
        public string $nombre,
        public string $estado,
        public string $estadoNombre,
        public bool $esVigente,
        public ?int $proyectoId,
        public ?int $oportunidadId,
        public string $monedaCodigo,
        public bool $usaApu,
        public string $gastosGeneralesPct,
        public string $utilidadPct,
        public string $igvPct,
        public string $costoDirecto,
        public string $gastosGenerales,
        public string $utilidad,
        public string $igv,
        public string $total,
        public string $sinDetallar,
        public array $partidas,
    ) {}
}
