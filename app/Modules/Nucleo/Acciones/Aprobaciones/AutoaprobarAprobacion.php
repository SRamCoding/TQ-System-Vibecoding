<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Firma el paso propio con permiso, bajo el tope de la regla y con motivo; queda para revisión (plan 1.7, D6). */
final class AutoaprobarAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.autoaprobar';
    }

    public function nombre(): string
    {
        return 'Aprobar lo que uno mismo pidió';
    }

    public function campos(): array
    {
        return [
            Campo::texto('documento_type', 'Tipo de documento', maximo: 120),
            Campo::entero('documento_id', 'Documento')->conReglas('min:1'),
            Campo::texto('transicion', 'Paso que se aprueba', maximo: 40),
            Campo::texto('motivo', 'Por qué lo apruebas tú', maximo: 2000),
        ];
    }

    public function permiso(): string
    {
        return AprobacionesService::PERMISO_AUTOAPROBAR;
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->autoaprobar($datos['documento_type'], $datos['documento_id'], $datos['transicion'], $usuario, $datos['motivo']);
    }
}
