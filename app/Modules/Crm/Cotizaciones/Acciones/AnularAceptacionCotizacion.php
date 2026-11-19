<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Anula la aceptación: vuelve a emitida, con motivo (plan 2.22, D5). Acción propia de «anular_aceptacion». */
final class AnularAceptacionCotizacion extends Accion
{
    public function clave(): string
    {
        return DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::ANULAR_ACEPTACION;
    }

    public function nombre(): string
    {
        return 'Anular la aceptación de la cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::motivoDe('motivo_id', 'Motivo', DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::ANULAR_ACEPTACION),
            Campo::texto('motivo_detalle', 'Detalle del motivo', maximo: 1000)->opcional(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->anularAceptacion($datos['cotizacion_id'], $datos['version'], new MotivoElegido($datos['motivo_id'], $datos['motivo_detalle'] ?? null), $datos['comentario'] ?? null, $usuario);
    }
}
