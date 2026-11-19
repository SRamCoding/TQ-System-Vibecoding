<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** «Traer al proyecto» (plan 2.9, D6): vincula el presupuesto al proyecto y lo aprueba. Es la transición de acción propia: su permiso es el de la transición. */
final class TraerPresupuestoAlProyecto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.traer-presupuesto-al-proyecto';
    }

    public function nombre(): string
    {
        return 'Traer presupuesto al proyecto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1'),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.traer_al_proyecto';
    }

    public function ejecutar(array $datos, User $usuario): Presupuesto
    {
        return app(ArmadoPresupuestoService::class)->traerAlProyecto($datos['presupuesto_id'], $datos['proyecto_id'], $datos['version'], $usuario);
    }
}
