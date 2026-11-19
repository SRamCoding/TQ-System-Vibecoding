<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\ConfiguracionInicial;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;

/** Seguir después desde Configuración: apaga la bienvenida para quien la ejecuta (plan 1.13b, D1). Las reglas están en `ConfiguracionInicialService`. */
final class SeguirDespues extends Accion
{
    public function clave(): string
    {
        return 'nucleo.configuracion-inicial.seguir-despues';
    }

    public function nombre(): string
    {
        return 'Seguir la configuración inicial después (apagar la bienvenida)';
    }

    public function campos(): array
    {
        return [];
    }

    public function permiso(): string
    {
        return ConfiguracionInicialService::PERMISO;
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        app(ConfiguracionInicialService::class)->apagarBienvenida($usuario);

        return null;
    }
}
