<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Firma el paso que sigue de una aprobación (plan 1.7). Le toca a quien ocupa la jefatura de ese paso. */
final class FirmarAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.firmar';
    }

    public function nombre(): string
    {
        return 'Firmar una aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::texto('documento_type', 'Tipo de documento', maximo: 120),
            Campo::entero('documento_id', 'Documento')->conReglas('min:1'),
            Campo::texto('transicion', 'Paso que se aprueba', maximo: 40),
            // Plan 2.22, P6: la versión del documento al abrir la bandeja; sin ella (sin interfaz) se firma lo actual.
            Campo::entero('version', 'Versión')->opcional()->conReglas('min:1')->deControl(),
        ];
    }

    /** Sin permiso: quién firma lo dicen las reglas de aprobación (plan 1.5, D7; plan 1.7, D1). */
    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->firmar($datos['documento_type'], $datos['documento_id'], $datos['transicion'], $usuario, $datos['version'] ?? null);
    }
}
