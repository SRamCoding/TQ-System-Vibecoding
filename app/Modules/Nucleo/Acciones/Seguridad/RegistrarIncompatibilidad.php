<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega al catálogo dos permisos que una persona no debería juntar. Quien ya los junta queda pendiente de control. Devuelve el id del par. */
final class RegistrarIncompatibilidad extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'registrar-incompatibilidad';
    }

    public function nombre(): string
    {
        return 'Registrar permisos incompatibles';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [
            Campo::entero('permiso_uno_id', 'Permiso')->conReglas('exists:permissions,id'),
            Campo::entero('permiso_otro_id', 'Incompatible con')->conReglas('exists:permissions,id'),
            Campo::texto('motivo', 'Motivo', maximo: 300),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->seguridad->registrarIncompatibilidad($datos['permiso_uno_id'], $datos['permiso_otro_id'], $datos['motivo']);
    }
}
