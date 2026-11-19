<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia lo escrito de un insumo en un APU: cantidad, desperdicio, rendimiento, cuadrilla o precio (plan 2.9, D4). */
final class EditarInsumoApu extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.editar-insumo-apu';
    }

    public function nombre(): string
    {
        return 'Editar insumo del APU';
    }

    public function campos(): array
    {
        return [
            Campo::entero('linea_id', 'Línea del APU')->conReglas('min:1'),
            Campo::entero('insumo_id', 'Insumo')->conReglas('min:1'),
            Campo::decimal('cantidad_neta', 'Cantidad neta (material o subcontrato)', escala: 5)->opcional(),
            Campo::decimal('desperdicio_pct', 'Desperdicio (%)')->opcional(),
            Campo::decimal('rendimiento', 'Rendimiento propio (si no, el de la partida)', escala: 4)->opcional(),
            Campo::texto('unidad_rendimiento', 'Rendimiento por', maximo: 10)->conReglas('in:hora,jornada')->opcional(),
            Campo::decimal('cuadrilla', 'Cuadrilla')->opcional(),
            Campo::decimal('precio', 'Precio', escala: 4)->opcional(),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    /** Lo que no viene toma el valor por defecto de DatosLineaApu (cuadrilla 1, sin desperdicio). */
    public function ejecutar(array $datos, User $usuario): LineaApu
    {
        return app(ArmadoPresupuestoService::class)->editarLineaApu($datos['linea_id'], new DatosLineaApu(...array_filter([
            'insumoId' => $datos['insumo_id'],
            'cantidadNeta' => $datos['cantidad_neta'] ?? null,
            'desperdicioPct' => $datos['desperdicio_pct'] ?? null,
            'rendimiento' => $datos['rendimiento'] ?? null,
            'unidadRendimiento' => $datos['unidad_rendimiento'] ?? null,
            'cuadrilla' => $datos['cuadrilla'] ?? null,
            'precio' => $datos['precio'] ?? null,
        ], fn (mixed $valor): bool => $valor !== null)), $datos['version']);
    }
}
