<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

/** Mientras no exista Contratos (plan 2.61, D10): no se sabe la venta de ningún proyecto. */
final class SinVentaDelProyecto implements VentaDelProyecto
{
    public function venta(int $proyectoId): ?string
    {
        return null;
    }
}
