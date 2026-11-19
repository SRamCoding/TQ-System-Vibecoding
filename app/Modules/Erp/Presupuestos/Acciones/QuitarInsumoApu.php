<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Quita un insumo del análisis de una partida (plan 2.9). */
final class QuitarInsumoApu extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.quitar-insumo-apu';
    }

    public function nombre(): string
    {
        return 'Quitar insumo del APU';
    }

    public function campos(): array
    {
        return [
            Campo::entero('linea_id', 'Línea del APU')->conReglas('min:1'),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): null
    {
        app(ArmadoPresupuestoService::class)->quitarLineaApu($datos['linea_id'], $datos['version']);

        return null;
    }
}
