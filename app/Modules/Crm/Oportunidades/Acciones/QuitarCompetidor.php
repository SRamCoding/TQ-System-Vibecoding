<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Quita un competidor de una oportunidad abierta (plan 2.21, D4; D10: con editar). */
class QuitarCompetidor extends Accion
{
    public function clave(): string
    {
        return 'crm.oportunidades.quitar-competidor';
    }

    public function nombre(): string
    {
        return 'Quitar competidor';
    }

    public function campos(): array
    {
        return [
            Campo::entero('competidor_id', 'Competidor')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR;
    }

    final public function ejecutar(array $datos, User $usuario): null
    {
        app(OportunidadesService::class)->quitarCompetidor($datos['competidor_id'], $datos['version'], $usuario);

        return null;
    }
}
