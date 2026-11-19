<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Excepciones;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** Una regla de la cotización impidió la operación (plan 2.22). No se escribe nada. */
final class CotizacionRechazada extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
