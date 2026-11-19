<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Una cuenta al registrarla (plan 1.3b, D5). Banco, número y moneda no cambian después; el CCI solo se completa
 * si quedó vacío. El número y el CCI pueden llegar con guiones o espacios: se guardan solo con dígitos.
 */
final readonly class DatosCuentaBancaria
{
    public function __construct(
        public int $bancoPartyId,
        public string $numeroCuenta,
        public string $codigoMoneda,
        public ?string $cci = null,
        public bool $esDetraccion = false,
        public bool $esPrincipal = false,
    ) {}
}
