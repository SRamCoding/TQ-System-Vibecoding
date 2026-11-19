<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un paso del asistente de configuración inicial que no existe (plan 1.13). */
final class PasoConfiguracionRechazado extends ErrorDeNegocio
{
    public static function noExiste(string $paso): self
    {
        return new self("No existe el paso de configuración «{$paso}».");
    }
}
