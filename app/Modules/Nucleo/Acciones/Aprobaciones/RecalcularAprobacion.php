<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Vuelve a calcular las firmas de una aprobación pedida con las reglas de ahora (plan 1.7, D5). */
final class RecalcularAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.recalcular';
    }

    public function nombre(): string
    {
        return 'Recalcular las firmas de una aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::texto('documento_type', 'Tipo de documento', maximo: 120),
            Campo::entero('documento_id', 'Documento')->conReglas('min:1'),
            Campo::texto('transicion', 'Paso que se aprueba', maximo: 40),
        ];
    }

    public function permiso(): string
    {
        return 'aprobaciones.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->recalcular($datos['documento_type'], $datos['documento_id'], $datos['transicion'], $usuario);
    }
}
