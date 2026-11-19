<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Acciones\RegistrarMiCumpleanos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Quien gestiona usuarios registra el cumpleaños de otra persona desde su ficha (plan 1.4b, D6), con la versión de la
 * cuenta leída al abrir.
 */
final class RegistrarCumpleanosUsuario extends Accion
{
    public function __construct(private readonly PreferenciasUsuarioService $preferencias) {}

    public function clave(): string
    {
        return 'nucleo.seguridad.registrar-cumpleanos-usuario';
    }

    public function nombre(): string
    {
        return 'Registrar cumpleaños';
    }

    public function permiso(): string
    {
        return 'usuarios.gestionar';
    }

    public function campos(): array
    {
        return [
            Campo::entero('usuario_id', 'Usuario')->conReglas('exists:users,id'),
            ...RegistrarMiCumpleanos::camposDeCumpleanos(),
            Campo::entero('version', 'Versión')->deControl(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->preferencias->registrarCumpleanos($datos['usuario_id'], $datos['cumpleanos_dia'], $datos['cumpleanos_mes'], $datos['version']);

        return null;
    }
}
