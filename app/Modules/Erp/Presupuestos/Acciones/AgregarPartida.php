<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega una partida al árbol en borrador, en la raíz o bajo un padre (plan 2.1, D5). */
final class AgregarPartida extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.agregar-partida';
    }

    public function nombre(): string
    {
        return 'Agregar partida';
    }

    public function campos(): array
    {
        return [
            Campo::entero('arbol_id', 'Árbol')->conReglas('min:1'),
            Campo::entero('padre_id', 'Partida padre')->conReglas('min:1')->opcional(),
            Campo::texto('codigo', 'Código', maximo: 30),
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('responsable_user_id', 'Responsable')->conReglas('min:1'),
            Campo::booleano('es_gastos_generales', 'Es de gastos generales')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaControl
    {
        return app(ArbolPartidasService::class)->agregarPartida(
            $datos['arbol_id'],
            $datos['padre_id'] ?? null,
            $datos['codigo'],
            $datos['nombre'],
            $datos['responsable_user_id'],
            $datos['es_gastos_generales'] ?? false,
        );
    }
}
