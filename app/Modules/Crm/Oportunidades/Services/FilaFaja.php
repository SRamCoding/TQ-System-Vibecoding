<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use Carbon\CarbonImmutable;

/**
 * Una oportunidad como la ven la faja y las tablas de sus lugares (plan 2.21b, D9; pedido B5): sin modelos de otros
 * módulos. Montos como texto decimal. Los datos de las salas son nulos en las zonas, y al revés.
 */
final readonly class FilaFaja
{
    public function __construct(
        public int $id,
        /** El número real de la serie OP (D7). */
        public string $numero,
        public string $nombre,
        public string $etapa,
        /** «Razón social · RUC» por CuentasService. */
        public string $cuenta,
        public string $monto,
        public string $moneda,
        public string $simbolo,
        /** En moneda local al T.C. de compra de hoy; nulo si no hay T.C. dentro de la tolerancia. */
        public ?string $montoEnSoles,
        public string $ejecutivo,
        public int $diasEnEtapa,
        /** Pasa los días máximos de su etapa (solo en una zona y si la etapa tiene máximo). */
        public bool $atascada,
        /** Siempre nula hasta CRM-05 (D5): la columna dice «—» y no hay punto ámbar. */
        public ?string $proximaAccion,
        public CarbonImmutable $cierreEstimado,
        /** La versión del bloqueo optimista, para capturarla al tomar la caja (D4). */
        public int $version,
        public ?CarbonImmutable $fechaCierre = null,
        /** Ganadas: la etiqueta del contrato por ContratosDeOportunidad; nula («—») mientras no exista Contratos. */
        public ?string $contrato = null,
        /** Perdidas: el motivo de su lista, con su detalle. */
        public ?string $motivo = null,
        public ?GanadorConocido $ganadorConocido = null,
        public ?string $ganador = null,
        public ?string $precio = null,
        public ?string $precioSimbolo = null,
        public bool $precioDesconocido = false,
    ) {}
}
