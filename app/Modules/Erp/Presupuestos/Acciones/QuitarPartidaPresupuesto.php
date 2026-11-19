<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Quita una partida y su análisis (plan 2.9). */
final class QuitarPartidaPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.quitar-partida-presupuesto';
    }

    public function nombre(): string
    {
        return 'Quitar partida del presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('partida_id', 'Partida')->conReglas('min:1'),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): null
    {
        app(ArmadoPresupuestoService::class)->quitarPartida($datos['partida_id'], $datos['version']);

        return null;
    }
}
