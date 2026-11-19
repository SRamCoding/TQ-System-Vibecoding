<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La versión siguiente, desde la última si ya salió al cliente, con su motivo (plan 2.22, D5). */
final class NuevaVersionCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.nueva-version-cotizacion';
    }

    public function nombre(): string
    {
        return 'Nueva versión de la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::motivoDe('motivo_id', 'Motivo', CotizacionesServiceProvider::FAMILIA_NUEVA_VERSION),
            Campo::texto('motivo_detalle', 'Detalle del motivo', maximo: 300)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return CotizacionesService::PERMISO_NUEVA_VERSION;
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->nuevaVersion($datos['cotizacion_id'], $datos['motivo_id'], $datos['motivo_detalle'] ?? null, $usuario);
    }
}
