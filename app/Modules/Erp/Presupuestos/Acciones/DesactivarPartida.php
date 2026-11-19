<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Desactiva una partida sin costos, sin monto y sin hijos activos del árbol en borrador (plan 2.1, D5). */
final class DesactivarPartida extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.desactivar-partida';
    }

    public function nombre(): string
    {
        return 'Desactivar partida';
    }

    public function campos(): array
    {
        return [
            Campo::entero('partida_id', 'Partida')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaControl
    {
        return app(ArbolPartidasService::class)->desactivar($datos['partida_id'], $datos['version']);
    }
}
