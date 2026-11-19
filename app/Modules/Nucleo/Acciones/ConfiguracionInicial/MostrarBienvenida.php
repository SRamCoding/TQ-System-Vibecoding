<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\ConfiguracionInicial;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;

/** Vuelve a encender la bienvenida para quien la ejecuta (plan 1.13b, D1). Las reglas están en `ConfiguracionInicialService`. */
final class MostrarBienvenida extends Accion
{
    public function clave(): string
    {
        return 'nucleo.configuracion-inicial.mostrar-bienvenida';
    }

    public function nombre(): string
    {
        return 'Volver a mostrar la bienvenida de la configuración inicial';
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
        app(ConfiguracionInicialService::class)->encenderBienvenida($usuario);

        return null;
    }
}
