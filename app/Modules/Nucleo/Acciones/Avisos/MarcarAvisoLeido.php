<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Avisos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\NotificacionesService;

/** La persona leyó uno de sus avisos: sale de la campana y queda en «Mis avisos» (plan 1.4y, D1). Sin permiso: son sus propios avisos; uno ajeno se rechaza como uno que no existe. */
final class MarcarAvisoLeido extends Accion
{
    public function __construct(private readonly NotificacionesService $avisos) {}

    public function clave(): string
    {
        return 'nucleo.marcar-aviso-leido';
    }

    public function nombre(): string
    {
        return 'Marcar aviso como leído';
    }

    public function campos(): array
    {
        return [Campo::entero('aviso_id', 'Aviso')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->avisos->marcarLeido($usuario, $datos['aviso_id']);

        return null;
    }
}
