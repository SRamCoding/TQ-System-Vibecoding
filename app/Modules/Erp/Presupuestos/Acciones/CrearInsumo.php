<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea un insumo del catálogo del APU (plan 2.9, D8). */
final class CrearInsumo extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.crear-insumo';
    }

    public function nombre(): string
    {
        return 'Crear insumo';
    }

    public function campos(): array
    {
        return [
            Campo::texto('codigo', 'Código', maximo: 30),
            Campo::texto('tipo', 'Tipo', maximo: 15)->conReglas('in:material,mano_obra,equipo,subcontrato'),
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('unidad_medida_id', 'Unidad de medida')->conReglas('min:1'),
            Campo::decimal('precio_referencia', 'Precio de referencia', escala: 4)->opcional(),
            Campo::entero('producto_id', 'Producto del catálogo')->conReglas('min:1')->opcional(),
            Campo::entero('categoria_costo_hora_id', 'Categoría de costo por hora')->conReglas('min:1')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.insumos.editar';
    }

    public function ejecutar(array $datos, User $usuario): Insumo
    {
        return app(InsumosService::class)->crear(new DatosInsumo(
            $datos['codigo'],
            $datos['nombre'],
            $datos['tipo'],
            $datos['unidad_medida_id'],
            $datos['precio_referencia'] ?? null,
            $datos['producto_id'] ?? null,
            $datos['categoria_costo_hora_id'] ?? null,
        ));
    }
}
