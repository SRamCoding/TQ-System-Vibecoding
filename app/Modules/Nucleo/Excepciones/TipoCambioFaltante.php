<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** No hay tipo de cambio registrado para la fecha (ni dentro de la tolerancia configurada). */
final class TipoCambioFaltante extends ErrorDeNegocio
{
    public static function para(string $moneda, string $fecha): self
    {
        return new self("No hay tipo de cambio de {$moneda} para el {$fecha}. Regístralo en Configuración → Tipos de cambio.");
    }
}
