<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\SeguridadService;

/**
 * Registra al gerente general al instalar (plan 1.10, D2), con `SeguridadService::registrarGerenteGeneral()`: sin él
 * el panel no arranca. Su persona (o la que ya exista con ese documento), su cuenta y el rol `gerencia general`, a
 * nombre del usuario de sistema. Se niega si ya hay uno activo: el siguiente se lo asigna un administrador en «Usuarios».
 */
final class GerenteGeneral extends ComandoCuentaInicial
{
    protected $signature = 'seguridad:gerente-general'.self::OPCIONES;

    protected $description = 'Registra la cuenta del gerente general (solo si no hay ninguna activa)';

    protected function yaHay(SeguridadService $seguridad): ?SeguridadRechazada
    {
        return $seguridad->hayGerenteGeneralActivo() ? SeguridadRechazada::yaHayGerenteGeneral() : null;
    }

    protected function registrar(SeguridadService $seguridad, DatosParty $persona, string $email, string $clave, User $sistema): int
    {
        return $seguridad->registrarGerenteGeneral($persona, $email, $clave, $sistema);
    }

    protected function hecho(): string
    {
        return 'Gerente general registrado';
    }
}
