<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/**
 * Una regla del catálogo impidió la operación (plan 2.60). Vive en `Services/` porque es parte del contrato de C4: otros
 * módulos la atrapan.
 */
final class CatalogoRechazado extends ErrorDeNegocio
{
    public static function noExiste(): self
    {
        return new self('Ese producto no existe en el catálogo.');
    }
}
