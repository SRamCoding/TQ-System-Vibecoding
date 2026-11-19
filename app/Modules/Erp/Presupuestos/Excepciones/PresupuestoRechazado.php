<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Excepciones;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** Una regla del presupuesto o del catálogo de insumos impidió la operación (plan 2.9). No se escribe nada. */
final class PresupuestoRechazado extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
