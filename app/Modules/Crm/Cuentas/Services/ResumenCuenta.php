<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Enums\MotivoNoUsable;
use Carbon\CarbonImmutable;

/** Una cuenta vista desde otro módulo o desde una pantalla (plan 2.20, D12): de solo lectura, nunca el modelo. */
final readonly class ResumenCuenta
{
    public function __construct(
        public int $id,
        public int $partyId,
        /** «razón social · RUC», como la etiqueta de la ficha. */
        public string $etiqueta,
        public int $ejecutivoUserId,
        public string $ejecutivoNombre,
        /** Con el tipo «Entidad pública» vigente en la fecha pedida (D3). */
        public bool $esPublica,
        public ?int $nivelGobiernoId,
        public ?string $nivelGobiernoNombre,
        /** Nula: prospecto (D8). */
        public ?CarbonImmutable $clienteDesde,
        /** Activa y con su ficha activa: se le puede colgar trabajo nuevo (D9). */
        public bool $usable,
        public ?MotivoNoUsable $motivoNoUsable,
        /** De dónde llegó la cuenta: el origen por defecto de sus oportunidades (plan 2.21, D7). */
        public int $origenComercialId,
        /** Plan 2.0, D5: cuál es, con el origen «Otro»; la oportunidad lo hereda con el origen. */
        public ?string $origenDetalle = null,
    ) {}

    public function esCliente(): bool
    {
        return $this->clienteDesde !== null;
    }

    /** Para un selector: la etiqueta con su marca si no es usable («… (Inactiva)»). */
    public function etiquetaParaSelector(): string
    {
        return $this->motivoNoUsable === null ? $this->etiqueta : "{$this->etiqueta} ({$this->motivoNoUsable->etiqueta()})";
    }
}
