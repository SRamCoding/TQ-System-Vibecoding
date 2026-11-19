<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** El par deja de revisarse y sus cruces abiertos se cierran. */
final class DesactivarIncompatibilidad extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'desactivar-incompatibilidad';
    }

    public function nombre(): string
    {
        return 'Desactivar permisos incompatibles';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [Campo::entero('incompatibilidad_id', 'Par')->conReglas('exists:incompatibilidades_permisos,id'), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->desactivarIncompatibilidad($datos['incompatibilidad_id'], $datos['version'], $usuario);

        return null;
    }
}
