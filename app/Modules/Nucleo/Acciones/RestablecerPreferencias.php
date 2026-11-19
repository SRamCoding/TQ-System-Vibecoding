<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Vuelve un grupo de preferencias de quien la ejecuta a como viene (plan 1.4g, D6). Sin permiso: son sus preferencias.
 */
final class RestablecerPreferencias extends Accion
{
    public function __construct(private readonly PreferenciasUsuarioService $preferencias) {}

    public function clave(): string
    {
        return 'nucleo.restablecer-preferencias';
    }

    public function nombre(): string
    {
        return 'Restablecer preferencias';
    }

    public function campos(): array
    {
        return [Campo::opcion('grupo', 'Grupo', GrupoPreferencia::class)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->preferencias->restablecer($usuario, $datos['grupo']);

        return null;
    }
}
