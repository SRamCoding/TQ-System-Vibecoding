<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * «Armar el árbol» desde el presupuesto aprobado (plan 2.9b, D7): la propuesta se arma de nuevo en el servidor (nunca
 * se confía en la que mostró la pantalla) y se confirma con los ajustes de la persona (D5): nombres y responsables
 * como «PC-01.01=…», y las partidas que quita. Es el origen «presupuesto»; cada origen es su propia acción.
 */
final class ArmarArbolDesdePresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.armar-arbol-desde-presupuesto';
    }

    public function nombre(): string
    {
        return 'Armar el árbol desde el presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1'),
            Campo::entero('responsable_user_id', 'Responsable de todas')->conReglas('min:1')->opcional(),
            Campo::listaDeTextos('nombres', 'Nombres cambiados', maximo: 240)->opcional(),
            Campo::listaDeTextos('responsables', 'Responsables cambiados', maximo: 60)->opcional(),
            Campo::listaDeTextos('quitar', 'Partidas que no van', maximo: 30)->opcional(),
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
        $propuesta = app(ConsultaArbol::class)->propuestaDesdePresupuesto($datos['proyecto_id']);
        $ajustes = AjustesPropuesta::desdeCampos($datos['nombres'] ?? [], $datos['responsables'] ?? [], $datos['quitar'] ?? [], $datos['responsable_user_id'] ?? null);

        return app(ArbolPartidasService::class)->crearDesdePropuesta($propuesta, $ajustes, $usuario);
    }
}
