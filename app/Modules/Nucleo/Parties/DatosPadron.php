<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Lo que devuelve el padrón de SUNAT para un documento, para precargar el alta (diseño del CRM, «Nueva
 * cuenta»). Ninguna fuente lo da todavía: la integración se construye después (plan 1.3, D12).
 */
final readonly class DatosPadron
{
    public function __construct(
        public ?string $razonSocial = null,
        public ?string $direccionFiscal = null,
        public ?int $ubigeoId = null,
        public ?string $estadoSunat = null,
        public ?string $condicionSunat = null,
    ) {}
}
