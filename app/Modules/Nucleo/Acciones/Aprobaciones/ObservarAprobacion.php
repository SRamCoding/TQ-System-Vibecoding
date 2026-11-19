<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Observa una aprobación: el documento vuelve a editable con lo que hay que corregir (plan 1.7, D4). */
final class ObservarAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.observar';
    }

    public function nombre(): string
    {
        return 'Observar una aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::texto('documento_type', 'Tipo de documento', maximo: 120),
            Campo::entero('documento_id', 'Documento')->conReglas('min:1'),
            Campo::texto('transicion', 'Paso que se aprueba', maximo: 40),
            // Plan 2.22, P6: la versión del documento al abrir la bandeja; sin ella (sin interfaz) se firma lo actual.
            Campo::entero('version', 'Versión')->opcional()->conReglas('min:1')->deControl(),
            Campo::entero('motivo_id', 'Motivo')->conReglas('min:1'),
            Campo::texto('comentario', 'Qué hay que corregir', maximo: 2000),
        ];
    }

    /** Sin permiso: observa quien debe firmar (plan 1.7, D4). */
    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->observar($datos['documento_type'], $datos['documento_id'], $datos['transicion'], $usuario, $datos['motivo_id'], $datos['comentario'], $datos['version'] ?? null);
    }
}
