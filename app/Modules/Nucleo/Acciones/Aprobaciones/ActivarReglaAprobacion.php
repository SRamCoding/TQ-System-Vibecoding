<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Activar una regla de aprobación (plan 1.7). Nunca se borra: lo ya firmado la cita. */
final class ActivarReglaAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.activar-regla';
    }

    public function nombre(): string
    {
        return 'Activar una regla de aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::entero('regla_id', 'Regla')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'aprobaciones.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->activarRegla($datos['regla_id'], $datos['version'], true);
    }
}
