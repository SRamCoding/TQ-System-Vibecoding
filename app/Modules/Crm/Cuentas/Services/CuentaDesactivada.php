<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

/** La cuenta desactivada y, si se pidió desactivar también su ficha y no se pudo, por qué (D9: solo se avisa). */
final readonly class CuentaDesactivada
{
    public function __construct(
        public ResumenCuenta $cuenta,
        public bool $fichaDesactivada,
        public ?string $avisoFicha = null,
    ) {}
}
