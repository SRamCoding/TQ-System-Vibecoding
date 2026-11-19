<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

/**
 * Cómo se nombra una oportunidad en el historial de la cuenta (plan 2.21, D13.3): la marca de cliente guarda solo su id
 * y Cuentas no importa nada de Oportunidades. Oportunidades registra la versión real («OP-2026-0012 · Nombre»); sin
 * ella vale la de fábrica, `OportunidadSinModulo`.
 */
interface OportunidadEnlazable
{
    public function etiqueta(int $oportunidadId): string;
}
