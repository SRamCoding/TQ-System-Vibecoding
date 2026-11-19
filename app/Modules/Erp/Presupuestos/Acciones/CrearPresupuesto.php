<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Crea un presupuesto en borrador, con su número PRE-AAAA-NNNN (plan 2.9). */
final class CrearPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.crear-presupuesto';
    }

    public function nombre(): string
    {
        return 'Crear presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('moneda_id', 'Moneda')->conReglas('min:1'),
            Campo::decimal('gastos_generales_pct', 'Gastos generales (%)'),
            Campo::decimal('utilidad_pct', 'Utilidad (%)'),
            Campo::decimal('igv_pct', 'IGV (%)')->opcional(),
            Campo::booleano('usa_apu', 'Con análisis de precios unitarios')->opcional(),
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): Presupuesto
    {
        // Lo que no viene toma el valor por defecto de DatosPresupuesto (IGV 18 %, con APU).
        return app(ArmadoPresupuestoService::class)->crear(new DatosPresupuesto(...array_filter([
            'nombre' => $datos['nombre'],
            'monedaId' => $datos['moneda_id'],
            'gastosGeneralesPct' => $datos['gastos_generales_pct'],
            'utilidadPct' => $datos['utilidad_pct'],
            'igvPct' => $datos['igv_pct'] ?? null,
            'usaApu' => $datos['usa_apu'] ?? null,
            'oportunidadId' => $datos['oportunidad_id'] ?? null,
        ], fn (mixed $valor): bool => $valor !== null)), $usuario);
    }
}
