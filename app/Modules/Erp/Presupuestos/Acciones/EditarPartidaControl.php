<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia el nombre y el responsable de una partida del árbol en borrador (plan 2.9b, D5). */
final class EditarPartidaControl extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.editar-partida';
    }

    public function nombre(): string
    {
        return 'Editar partida de control';
    }

    public function campos(): array
    {
        return [
            Campo::entero('partida_id', 'Partida')->conReglas('min:1'),
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('responsable_user_id', 'Responsable')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaControl
    {
        return app(ArbolPartidasService::class)->editarPartida($datos['partida_id'], $datos['nombre'], $datos['responsable_user_id'], $datos['version']);
    }
}
