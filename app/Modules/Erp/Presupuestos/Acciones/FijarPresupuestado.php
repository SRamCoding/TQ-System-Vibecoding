<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Fija el presupuestado de una hoja del árbol en borrador (plan 2.1, D5). */
final class FijarPresupuestado extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.fijar-presupuestado';
    }

    public function nombre(): string
    {
        return 'Fijar presupuestado de la partida';
    }

    public function campos(): array
    {
        return [
            Campo::entero('partida_id', 'Partida')->conReglas('min:1'),
            Campo::decimal('monto', 'Presupuestado'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaControl
    {
        return app(ArbolPartidasService::class)->fijarPresupuestado($datos['partida_id'], $datos['monto'], $datos['version']);
    }
}
