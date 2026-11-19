<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cantidad, precio, descuento y costo de una línea; la del presupuesto, solo su descuento (plan 2.22, D6 y D8). */
final class EditarLineaCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.editar-linea';
    }

    public function nombre(): string
    {
        return 'Editar línea de la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('linea_id', 'Línea')->conReglas('min:1'),
            Campo::entero('version', 'Versión de la cotización')->deControl(),
            Campo::decimal('cantidad', 'Cantidad', escala: 3),
            Campo::decimal('precio_unitario', 'Precio unitario sin IGV', escala: 4),
            Campo::decimal('costo_unitario', 'Costo unitario', escala: 4),
            Campo::decimal('descuento_porcentaje', 'Descuento (%)')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return CotizacionesService::PERMISO_EDITAR;
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->editarLinea($datos['linea_id'], $datos['version'], new DatosLinea($datos['cantidad'], $datos['precio_unitario'], $datos['costo_unitario'], $datos['descuento_porcentaje'] ?? '0'), $usuario);
    }
}
