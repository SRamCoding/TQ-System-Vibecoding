<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Pasa un presupuesto en borrador de con APU a sin APU o al revés (plan 2.9, D9). Sin APU descarta los análisis: pide confirmación. */
final class CambiarUsaApu extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.cambiar-usa-apu';
    }

    public function nombre(): string
    {
        return 'Cambiar presupuesto con o sin APU';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::booleano('usa_apu', 'Con análisis de precios unitarios'),
            Campo::booleano('confirma_descartar_apu', 'Confirmo descartar los análisis')->opcional(),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): Presupuesto
    {
        return app(ArmadoPresupuestoService::class)->cambiarUsaApu($datos['presupuesto_id'], $datos['usa_apu'], $datos['confirma_descartar_apu'] ?? false, $datos['version']);
    }
}
