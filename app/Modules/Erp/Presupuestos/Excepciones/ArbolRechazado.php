<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Excepciones;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** Una regla del árbol de partidas impidió la operación (plan 2.1, D5). No se escribe nada. */
final class ArbolRechazado extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
