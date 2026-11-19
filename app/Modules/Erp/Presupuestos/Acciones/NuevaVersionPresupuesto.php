<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea la versión siguiente de un presupuesto aprobado, en borrador (plan 2.9, D6; adenda). */
final class NuevaVersionPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.nueva-version-presupuesto';
    }

    public function nombre(): string
    {
        return 'Nueva versión del presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto aprobado')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.nueva-version';
    }

    public function ejecutar(array $datos, User $usuario): Presupuesto
    {
        return app(ArmadoPresupuestoService::class)->nuevaVersion($datos['presupuesto_id'], $usuario);
    }
}
