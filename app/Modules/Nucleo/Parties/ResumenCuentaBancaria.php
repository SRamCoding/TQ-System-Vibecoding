<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Lo que otro módulo recibe de una cuenta (tesorería al pagar, cobranzas): id, etiqueta («Banco Ejemplo ·
 * 1911234567012 · PEN»), moneda y CCI. En pantalla, número y CCI solo para quien tiene `campos.ver-datos_bancarios`.
 */
final readonly class ResumenCuentaBancaria
{
    public function __construct(
        public int $id,
        public string $etiqueta,
        public string $codigoMoneda,
        public string $numeroCuenta,
        public ?string $cci,
        public bool $esDetraccion,
    ) {}
}
