<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Acciones;

use App\Modules\Erp\Valorizaciones\Models\Subcontrato;
use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Observaciones 6, punto 12: con el subcontrato al 100 % y su conformidad, se devuelve la retención acumulada. */
final class LiberarRetencion extends Accion
{
    public function clave(): string
    {
        return 'erp.valorizaciones.liberar-retencion';
    }

    public function nombre(): string
    {
        return 'Liberar retención';
    }

    public function campos(): array
    {
        return [
            Campo::entero('subcontrato', 'Subcontrato')->conReglas('min:1'),
            Campo::fecha('conformidad', 'Fecha de conformidad'),
            Campo::texto('nota', 'Nota', 500),
        ];
    }

    public function permiso(): string
    {
        return SubcontratosService::PERMISO_LIBERAR;
    }

    public function ejecutar(array $datos, User $usuario): Subcontrato
    {
        return app(SubcontratosService::class)->liberarRetencion($datos['subcontrato'], $datos['conformidad'], $datos['nota']);
    }
}
