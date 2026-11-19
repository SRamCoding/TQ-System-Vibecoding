<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Desactiva un insumo: no entra en análisis nuevos (plan 2.9, D8). */
final class DesactivarInsumo extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.desactivar-insumo';
    }

    public function nombre(): string
    {
        return 'Desactivar insumo';
    }

    public function campos(): array
    {
        return [
            Campo::entero('insumo_id', 'Insumo')->conReglas('min:1'),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.insumos.editar';
    }

    public function ejecutar(array $datos, User $usuario): Insumo
    {
        return app(InsumosService::class)->desactivar($datos['insumo_id'], $datos['version']);
    }
}
