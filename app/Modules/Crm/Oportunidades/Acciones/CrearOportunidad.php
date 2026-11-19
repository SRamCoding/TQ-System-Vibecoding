<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Secciones\SeccionDeOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;

/** Nueva oportunidad en una cuenta (plan 2.21, D5, D6, D7, D8), con las secciones de otros módulos (D12.4). */
final class CrearOportunidad extends Accion
{
    public function clave(): string
    {
        return 'crm.oportunidades.crear';
    }

    public function nombre(): string
    {
        return 'Nueva oportunidad';
    }

    public function campos(): array
    {
        return [
            ...CamposOportunidad::datos(conCuenta: true),
            ...app(SeccionesDeOportunidad::class)->campos(SeccionDeOportunidad::AL_CREAR),
        ];
    }

    public function permiso(): string
    {
        return OportunidadesService::PERMISO_CREAR;
    }

    public function ejecutar(array $datos, User $usuario): ResumenOportunidad
    {
        return app(OportunidadesService::class)->crear($datos['cuenta_id'], CamposOportunidad::aDatos($datos), $datos, $usuario);
    }
}
