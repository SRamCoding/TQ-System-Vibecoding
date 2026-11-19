<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/**
 * Una regla del proyecto impidió la operación (plan 2.1). No se escribe nada. Vive en `Services/` porque es parte del
 * contrato de C2: otros módulos la atrapan, y entre módulos solo se usan `Services/` y `Events/` (ARCHITECTURE.md).
 */
final class ProyectoRechazado extends ErrorDeNegocio
{
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }
}
