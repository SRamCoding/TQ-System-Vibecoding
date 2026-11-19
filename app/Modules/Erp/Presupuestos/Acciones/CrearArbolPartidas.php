<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea el árbol de partidas de un proyecto, en borrador (plan 2.1, D5). */
final class CrearArbolPartidas extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.crear-arbol';
    }

    public function nombre(): string
    {
        return 'Crear árbol de partidas';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1'),
            Campo::decimal('costo_presupuestado', 'Costo presupuestado'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function ejecutar(array $datos, User $usuario): ArbolPartidas
    {
        // Plan 2.11, D11: armar el árbol pide ver costos.
        app(ArbolPartidasService::class)->exigirVerCostos($usuario);

        return app(ArbolPartidasService::class)->crear($datos['proyecto_id'], $datos['costo_presupuestado'], $usuario);
    }
}
