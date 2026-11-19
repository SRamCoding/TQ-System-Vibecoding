<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Una partida de control propuesta (plan 2.9b, D1, D2 y D6): su código y nombre, de quién cuelga, su monto si es hoja
 * y de dónde salió. El origen viaja con ella aunque se renombre en la propuesta: es histórico.
 */
final readonly class NodoPropuesto
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $padreCodigo,
        public bool $esHoja,
        /** Solo en hojas; los padres suman sus hijas. */
        public ?string $monto,
        public bool $esGastosGenerales = false,
        public ?int $origenTituloId = null,
        public ?int $origenPartidaId = null,
        /** «título 01», «01.01 · Tendido de cable UTP», «gastos generales del presupuesto». */
        public ?string $origen = null,
    ) {}
}
