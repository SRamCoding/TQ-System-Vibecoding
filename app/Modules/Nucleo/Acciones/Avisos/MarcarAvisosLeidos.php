<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Avisos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\NotificacionesService;

/** «Marcar todos como leídos» de la campana (plan 1.4y, D1): todos los avisos sin leer de la persona. Devuelve cuántos. */
final class MarcarAvisosLeidos extends Accion
{
    public function __construct(private readonly NotificacionesService $avisos) {}

    public function clave(): string
    {
        return 'nucleo.marcar-avisos-leidos';
    }

    public function nombre(): string
    {
        return 'Marcar todos los avisos como leídos';
    }

    public function campos(): array
    {
        return [];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->avisos->marcarTodosLeidos($usuario);
    }
}
