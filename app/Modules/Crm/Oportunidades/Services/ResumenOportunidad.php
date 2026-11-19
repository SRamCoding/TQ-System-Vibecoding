<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/** Lo que otros módulos leen de una oportunidad (C13; plan 2.21, D12): nunca el modelo. */
final readonly class ResumenOportunidad
{
    public function __construct(
        public int $id,
        public string $numero,
        public string $nombre,
        public int $cuentaId,
        public int $ejecutivoUserId,
        /** El código del estado: `prospecto`, …, `ganada`, `perdida`, `anulada`. */
        public string $etapa,
        /** Texto decimal, nunca float. */
        public string $monto,
        public string $moneda,
        public bool $abierta,
        public int $version,
    ) {}

    /** Para un selector: «OP-2026-0012 · Cableado del hospital». */
    public function etiqueta(): string
    {
        return "{$this->numero} · {$this->nombre}";
    }
}
