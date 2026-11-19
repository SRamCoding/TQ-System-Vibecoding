<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Una línea del catálogo o libre, «por catalogar» (plan 2.22, D6). */
final class AgregarLineaCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.agregar-linea';
    }

    public function nombre(): string
    {
        return 'Agregar línea a la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::entero('producto_id', 'Producto del catálogo')->opcional(),
            Campo::texto('descripcion', 'Descripción (por catalogar)', maximo: 500)->opcional(),
            Campo::elementoDe('unidad_medida_id', 'Unidad (por catalogar)', Catalogo::UnidadesMedida)->opcional(),
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

    public function ejecutar(array $datos, User $usuario): LineaDeCotizacion
    {
        return app(CotizacionesService::class)->agregarLinea($datos['cotizacion_id'], $datos['version'], new DatosLinea($datos['cantidad'], $datos['precio_unitario'], $datos['costo_unitario'], $datos['descuento_porcentaje'] ?? '0', $datos['producto_id'] ?? null, $datos['descripcion'] ?? null, $datos['unidad_medida_id'] ?? null), $usuario);
    }
}
