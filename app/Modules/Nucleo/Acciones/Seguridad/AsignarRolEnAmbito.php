<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\SeguridadService;

/** Da un rol solo dentro de un ámbito (una unidad organizativa), con vigencia opcional. Devuelve el id de la asignación. */
final class AsignarRolEnAmbito extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'asignar-rol-en-ambito';
    }

    public function nombre(): string
    {
        return 'Asignar rol en un ámbito';
    }

    public function campos(): array
    {
        return [
            self::campoUsuario(),
            self::campoRol(),
            Campo::texto('ambito_type', 'Tipo de ámbito', maximo: 120)->conReglas('in:'.implode(',', SeguridadService::AMBITOS)),
            Campo::entero('ambito_id', 'Ámbito'),
            Campo::fecha('desde', 'Desde')->opcional(),
            Campo::fecha('hasta', 'Hasta')->opcional(),
            ...self::camposDeControl(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->seguridad->asignarRolEnAmbito(
            $datos['usuario_id'],
            $datos['rol_id'],
            $datos['ambito_type'],
            $datos['ambito_id'],
            $datos['desde'] ?? null,
            $datos['hasta'] ?? null,
            self::datosControl($datos),
            $usuario,
        );
    }
}
