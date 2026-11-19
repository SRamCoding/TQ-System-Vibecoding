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
 * «Armar el árbol» desde una plantilla (plan 2.11, paso 6; ADR 0022): el origen «plantilla» del panel de la 2.9b. La
 * propuesta se arma de nuevo en el servidor con la versión vigente; si no es la que la persona revisó, se rechaza
 * (D10; la versión revisada es obligatoria). Pide ver costos (D11). El costo presupuestado lo elige la persona (D8) y los ajustes van como en el origen «presupuesto».
 */
final class ArmarArbolDesdePlantilla extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.armar-arbol-desde-plantilla';
    }

    public function nombre(): string
    {
        return 'Armar el árbol desde una plantilla';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1'),
            Campo::entero('plantilla_id', 'Plantilla')->conReglas('min:1'),
            Campo::entero('plantilla_version_id', 'Versión revisada')->conReglas('min:1'),
            Campo::decimal('costo_presupuestado', 'Costo presupuestado')->conReglas('min:0'),
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
        app(ArbolPartidasService::class)->exigirVerCostos($usuario);
        $propuesta = app(ConsultaArbol::class)->propuestaDesdePlantilla($datos['proyecto_id'], $datos['plantilla_id'], $datos['plantilla_version_id'] ?? null, $datos['costo_presupuestado'], $usuario);
        $ajustes = AjustesPropuesta::desdeCampos($datos['nombres'] ?? [], $datos['responsables'] ?? [], $datos['quitar'] ?? [], $datos['responsable_user_id'] ?? null);

        return app(ArbolPartidasService::class)->crearDesdePropuesta($propuesta, $ajustes, $usuario);
    }
}
