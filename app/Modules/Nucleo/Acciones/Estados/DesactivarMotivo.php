<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Estados;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\MotivosService;

/** Deja de ofrecer un motivo; el historial viejo lo sigue mostrando. «Otro» no se desactiva (plan 1.5, D9). */
final class DesactivarMotivo extends Accion
{
    public function clave(): string
    {
        return 'nucleo.motivos.desactivar';
    }

    public function nombre(): string
    {
        return 'Desactivar motivo';
    }

    public function campos(): array
    {
        return [
            Campo::entero('motivo_id', 'Motivo')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'nucleo.motivos.editar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(MotivosService::class)->desactivar($datos['motivo_id'], $datos['version']);
    }
}
