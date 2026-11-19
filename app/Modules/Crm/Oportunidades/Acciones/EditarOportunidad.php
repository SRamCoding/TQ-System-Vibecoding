<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Edita una oportunidad abierta (plan 2.21, D10). La de las propias es EditarOportunidadPropia: el servicio revisa en
 * los dos casos con puedeSobrePropio.
 */
class EditarOportunidad extends Accion
{
    public function clave(): string
    {
        return 'crm.oportunidades.editar';
    }

    public function nombre(): string
    {
        return 'Editar oportunidad';
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            ...CamposOportunidad::datos(conCuenta: false),
        ];
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR;
    }

    final public function ejecutar(array $datos, User $usuario): ResumenOportunidad
    {
        return app(OportunidadesService::class)->editar($datos['oportunidad_id'], $datos['version'], CamposOportunidad::aDatos($datos), $usuario);
    }
}
