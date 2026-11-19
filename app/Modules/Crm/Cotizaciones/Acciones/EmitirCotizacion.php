<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Emite la versión: la congela y sale al cliente (plan 2.22, D5, D9 y D10). Acción propia de «emitir». */
final class EmitirCotizacion extends Accion
{
    public function clave(): string
    {
        return DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::EMITIR;
    }

    public function nombre(): string
    {
        return 'Emitir cotización';
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
        return app(CotizacionesService::class)->emitir($datos['cotizacion_id'], $datos['version'], $datos['comentario'] ?? null, $usuario);
    }
}
