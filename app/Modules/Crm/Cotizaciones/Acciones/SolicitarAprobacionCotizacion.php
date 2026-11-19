<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Pide la aprobación de una versión con el margen bajo el mínimo (plan 2.22, D14). Acción propia de «solicitar_aprobacion». */
final class SolicitarAprobacionCotizacion extends Accion
{
    public function clave(): string
    {
        return DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::SOLICITAR_APROBACION;
    }

    public function nombre(): string
    {
        return 'Solicitar aprobación de la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->solicitarAprobacion($datos['cotizacion_id'], $datos['version'], $datos['comentario'] ?? null, $usuario);
    }
}
