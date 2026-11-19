<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Excepciones\ReclasificacionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Mover un costo ya aprobado a otra partida, con motivo y comentario (plan 2.6, D5; v4, flujo 7: «reclasificar»). Queda
 * registrada con su permiso y sus campos; la pieza del núcleo que cambia el destino de una línea con montos llega con la
 * tarea 2.8, que solo llena `ejecutar()`. La línea se nombra por su imputación: así sirve para cualquier documento.
 */
final class ReclasificarGasto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.reclasificar-gasto';
    }

    public function nombre(): string
    {
        return 'Reclasificar gasto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('imputacion_id', 'Línea de costo')->conReglas('min:1'),
            Campo::entero('partida_destino_id', 'Partida destino')->conReglas('min:1'),
            Campo::entero('motivo_id', 'Motivo')->conReglas('min:1'),
            Campo::texto('comentario', 'Comentario', maximo: 500),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.reclasificar-gasto';
    }

    public function ejecutar(array $datos, User $usuario): never
    {
        throw ReclasificacionRechazada::todaviaNo();
    }
}
