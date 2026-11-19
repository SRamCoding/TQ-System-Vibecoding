<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Enlaza el presupuesto con APU de la oportunidad como una línea (C8; plan 2.22, D8). */
final class EnlazarPresupuestoCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.enlazar-presupuesto';
    }

    public function nombre(): string
    {
        return 'Enlazar presupuesto a la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::decimal('descuento_porcentaje', 'Descuento (%)')->opcional(),
        ];
    }

    public function permiso(): string
    {
        return CotizacionesService::PERMISO_EDITAR;
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->enlazarPresupuesto($datos['cotizacion_id'], $datos['version'], $datos['presupuesto_id'], $datos['descuento_porcentaje'] ?? '0', $usuario);
    }
}
