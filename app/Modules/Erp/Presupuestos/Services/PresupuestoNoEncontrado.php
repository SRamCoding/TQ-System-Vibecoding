<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** C8: el presupuesto pedido no existe (plan 2.9). */
final class PresupuestoNoEncontrado extends ErrorDeNegocio
{
    public static function id(int $presupuestoId): self
    {
        return new self("El presupuesto #{$presupuestoId} no existe.");
    }
}
