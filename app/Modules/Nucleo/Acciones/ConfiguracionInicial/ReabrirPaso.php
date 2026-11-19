<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\ConfiguracionInicial;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;

/** Reabrir un paso de la configuración inicial (plan 1.13, D3). Las reglas están en `ConfiguracionInicialService`. */
final class ReabrirPaso extends Accion
{
    public function clave(): string
    {
        return 'nucleo.configuracion-inicial.reabrir-paso';
    }

    public function nombre(): string
    {
        return 'Reabrir un paso de la configuración inicial';
    }

    public function campos(): array
    {
        return [Campo::texto('paso', 'Paso', 80)];
    }

    public function permiso(): string
    {
        return ConfiguracionInicialService::PERMISO;
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        app(ConfiguracionInicialService::class)->reabrir($datos['paso']);

        return null;
    }
}
