<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Pasa una o varias abiertas a otro ejecutivo, todo o nada (plan 2.21, D5). */
final class ReasignarOportunidades extends Accion
{
    public function clave(): string
    {
        return 'crm.oportunidades.reasignar';
    }

    public function nombre(): string
    {
        return 'Reasignar oportunidades';
    }

    public function campos(): array
    {
        return [
            Campo::listaDeIds('oportunidad_ids', 'Oportunidades', 'oportunidades', 'numero'),
            Campo::entero('ejecutivo_user_id', 'Nuevo ejecutivo')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl()->opcional(),
        ];
    }

    public function permiso(): string
    {
        return OportunidadesService::PERMISO_REASIGNAR;
    }

    public function ejecutar(array $datos, User $usuario): int
    {
        return app(OportunidadesService::class)->reasignar($datos['oportunidad_ids'], $datos['ejecutivo_user_id'], $datos['version'] ?? null);
    }
}
