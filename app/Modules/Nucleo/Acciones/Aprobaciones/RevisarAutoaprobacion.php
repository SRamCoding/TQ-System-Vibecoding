<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Marca revisada una autoaprobación: otra persona la miró después (plan 1.7, D6). */
final class RevisarAutoaprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.revisar-autoaprobacion';
    }

    public function nombre(): string
    {
        return 'Revisar una autoaprobación';
    }

    public function campos(): array
    {
        return [
            Campo::entero('firma_id', 'Firma')->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'aprobaciones.revisar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->revisarAutoaprobacion($datos['firma_id'], $usuario);
    }
}
