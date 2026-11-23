<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Excepciones;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** MVP rápido (t4-valorizaciones): una operación de subcontrato que las reglas no permiten. No se guarda nada. */
final class SubcontratoRechazado extends ErrorDeNegocio
{
    public static function conMotivo(string $motivo): self
    {
        return new self($motivo);
    }
}
