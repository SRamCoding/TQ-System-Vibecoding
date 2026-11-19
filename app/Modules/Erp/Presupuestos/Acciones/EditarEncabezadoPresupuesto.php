<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia el nombre, la moneda y los porcentajes de un presupuesto en borrador (plan 2.9). */
final class EditarEncabezadoPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.editar-encabezado-presupuesto';
    }

    public function nombre(): string
    {
        return 'Editar encabezado del presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('moneda_id', 'Moneda')->conReglas('min:1'),
            Campo::decimal('gastos_generales_pct', 'Gastos generales (%)'),
            Campo::decimal('utilidad_pct', 'Utilidad (%)'),
            Campo::decimal('igv_pct', 'IGV (%)')->opcional(),
            Campo::entero('version', 'Versión leída')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): Presupuesto
    {
        return app(ArmadoPresupuestoService::class)->editarEncabezado($datos['presupuesto_id'], new DatosPresupuesto(
            $datos['nombre'],
            $datos['moneda_id'],
            $datos['gastos_generales_pct'],
            $datos['utilidad_pct'],
            ...array_filter(['igvPct' => $datos['igv_pct'] ?? null], fn (mixed $valor): bool => $valor !== null),
        ), $datos['version']);
    }
}
