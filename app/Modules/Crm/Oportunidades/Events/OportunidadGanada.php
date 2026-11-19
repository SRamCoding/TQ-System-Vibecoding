<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lo que pasa al ERP sin reescribirse (C3, ADR 0020; plan 2.21, D3). Sale después de confirmar. Hasta que existan sus
 * módulos van vacíos la cotización aceptada y sus condiciones (plazo, forma de pago, garantía) y el presupuesto enlazado
 * (CRM-03), y la convocatoria de origen (CRM-04).
 */
final readonly class OportunidadGanada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $oportunidadId,
        public int $cuentaId,
        public int $partyId,
        /** Texto decimal, nunca float. */
        public string $monto,
        public string $moneda,
        public string $fechaCierreReal,
        public int $ejecutivoUserId,
        public ?int $cotizacionId = null,
        public ?string $plazo = null,
        public ?string $formaPago = null,
        public ?string $garantia = null,
        public ?int $presupuestoId = null,
        public ?int $convocatoriaId = null,
    ) {}
}
