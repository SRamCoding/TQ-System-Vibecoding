<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\ConfiguracionInicial;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;

/** Marcar un paso de la configuración inicial como listo (plan 1.13, D3). Las reglas están en `ConfiguracionInicialService`. */
final class MarcarPasoListo extends Accion
{
    public function clave(): string
    {
        return 'nucleo.configuracion-inicial.marcar-paso-listo';
    }

    public function nombre(): string
    {
        return 'Marcar un paso de la configuración inicial como listo';
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
        app(ConfiguracionInicialService::class)->marcarListo($datos['paso']);

        return null;
    }
}
