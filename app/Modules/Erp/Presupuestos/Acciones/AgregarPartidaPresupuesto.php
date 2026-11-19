<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega una partida con su metrado bajo un título; sin APU, con su precio unitario (plan 2.9, D9). */
final class AgregarPartidaPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.agregar-partida-presupuesto';
    }

    public function nombre(): string
    {
        return 'Agregar partida al presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::entero('titulo_id', 'Título')->conReglas('min:1'),
            Campo::texto('codigo', 'Código', maximo: 30),
            Campo::texto('descripcion', 'Descripción', maximo: 300),
            Campo::entero('unidad_medida_id', 'Unidad de medida')->conReglas('min:1'),
            Campo::decimal('metrado', 'Metrado', escala: 3),
            Campo::decimal('precio_unitario', 'Precio unitario (sin APU)', escala: 4)->opcional(),
            Campo::decimal('rendimiento', 'Rendimiento de la partida', escala: 4)->opcional(),
            Campo::texto('unidad_rendimiento', 'Rendimiento por', maximo: 10)->conReglas('in:hora,jornada')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): PartidaDePresupuesto
    {
        return app(ArmadoPresupuestoService::class)->agregarPartida(
            $datos['presupuesto_id'],
            $datos['titulo_id'],
            $datos['codigo'],
            $datos['descripcion'],
            $datos['unidad_medida_id'],
            $datos['metrado'],
            $datos['precio_unitario'] ?? null,
            $datos['rendimiento'] ?? null,
            $datos['unidad_rendimiento'] ?? null,
        );
    }
}
