<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia la descripción, la unidad, el metrado y, sin APU, el precio unitario de una partida (plan 2.9). */
final class EditarPartidaPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.editar-partida-presupuesto';
    }

    public function nombre(): string
    {
        return 'Editar partida del presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('partida_id', 'Partida')->conReglas('min:1'),
            Campo::texto('descripcion', 'Descripción', maximo: 300),
            Campo::entero('unidad_medida_id', 'Unidad de medida')->conReglas('min:1'),
            Campo::decimal('metrado', 'Metrado', escala: 3),
            Campo::decimal('precio_unitario', 'Precio unitario (sin APU)', escala: 4)->opcional(),
            Campo::decimal('rendimiento', 'Rendimiento de la partida', escala: 4)->opcional(),
            Campo::texto('unidad_rendimiento', 'Rendimiento por', maximo: 10)->conReglas('in:hora,jornada')->opcional(),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaDePresupuesto
    {
        return app(ArmadoPresupuestoService::class)->editarPartida(
            $datos['partida_id'],
            $datos['descripcion'],
            $datos['unidad_medida_id'],
            $datos['metrado'],
            $datos['precio_unitario'] ?? null,
            $datos['version'],
            $datos['rendimiento'] ?? null,
            $datos['unidad_rendimiento'] ?? null,
        );
    }
}
