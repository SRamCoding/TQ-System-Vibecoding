<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\SeguridadService;

/**
 * Crea la primera cuenta de administrador (plan 1.4, D8) con `SeguridadService::registrarPrimerAdministrador()`: su
 * persona (o la que ya exista con ese documento), su cuenta y el rol `administrador`, a nombre del usuario de sistema. Se niega si ya hay un
 * administrador activo: desde ahí, los demás los crea un administrador en «Usuarios».
 */
final class PrimerAdministrador extends ComandoCuentaInicial
{
    protected $signature = 'seguridad:primer-administrador'.self::OPCIONES;

    protected $description = 'Crea la primera cuenta de administrador (solo si no hay ninguna activa)';

    protected function yaHay(SeguridadService $seguridad): ?SeguridadRechazada
    {
        return $seguridad->hayAdministradorActivo() ? SeguridadRechazada::yaHayAdministrador() : null;
    }

    protected function registrar(SeguridadService $seguridad, DatosParty $persona, string $email, string $clave, User $sistema): int
    {
        return $seguridad->registrarPrimerAdministrador($persona, $email, $clave, $sistema);
    }

    protected function hecho(): string
    {
        return 'Administrador creado';
    }
}
