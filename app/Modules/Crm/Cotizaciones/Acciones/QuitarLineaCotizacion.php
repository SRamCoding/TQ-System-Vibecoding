<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Quita una línea del borrador; si es la del presupuesto, lo desenlaza (plan 2.22, D6 y D8). */
final class QuitarLineaCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.quitar-linea';
    }

    public function nombre(): string
    {
        return 'Quitar línea de la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('linea_id', 'Línea')->conReglas('min:1'),
            Campo::entero('version', 'Versión de la cotización')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return CotizacionesService::PERMISO_EDITAR;
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->quitarLinea($datos['linea_id'], $datos['version'], $usuario);
    }
}
