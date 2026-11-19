<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** El cliente acepta la versión: fecha y documento de respaldo (plan 2.22, D5 y P2). Acción propia de «aceptar». */
final class AceptarCotizacion extends Accion
{
    public function clave(): string
    {
        return DefinicionCotizacion::CLAVE.'.'.DefinicionCotizacion::ACEPTAR;
    }

    public function nombre(): string
    {
        return 'Aceptar cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::fecha('fecha_aceptacion', 'Fecha de aceptación'),
            Campo::opcion('respaldo_tipo', 'Documento de respaldo', TipoRespaldo::class),
            Campo::texto('respaldo_referencia', 'Número o referencia', maximo: 100),
            // Plan 2.22b: el archivo se sube aquí y queda adjunto a la cotización; sin interfaz, también el id de uno ya
            // adjunto a ella.
            Campo::archivo('respaldo_archivo', 'Archivo del respaldo')->opcional(),
            Campo::entero('respaldo_adjunto_id', 'Archivo ya adjunto')->opcional(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->aceptar($datos['cotizacion_id'], $datos['version'], $datos['fecha_aceptacion'], $datos['respaldo_tipo'], $datos['respaldo_referencia'], $datos['respaldo_adjunto_id'] ?? null, $datos['comentario'] ?? null, $usuario, $datos['respaldo_archivo'] ?? null);
    }
}
