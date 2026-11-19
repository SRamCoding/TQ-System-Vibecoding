<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use Carbon\CarbonImmutable;

/**
 * Una fila de «Mis aprobaciones» (plan 2.22, P6): el paso que le toca firmar a la persona, con el resumen ya filtrado
 * por sus permisos y la versión del documento leída al armarla (la firma la lleva para el bloqueo optimista).
 */
final readonly class PendienteDeFirma
{
    /** @param  list<DatoParaAprobar>  $datos */
    public function __construct(
        public int $firmaId,
        public string $documentoType,
        public int $documentoId,
        public string $transicion,
        /** «Cotización COT-2026-0184 v2». */
        public string $documento,
        /** La jefatura que firma este paso. */
        public string $paso,
        public string $solicitante,
        public CarbonImmutable $pedidaAt,
        public string $montoSoles,
        public int $version,
        public array $datos,
        public ?string $enlace,
    ) {}
}
