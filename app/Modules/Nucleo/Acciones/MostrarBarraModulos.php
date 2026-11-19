<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\NavegacionService;

/**
 * Cada persona prende o apaga su barra de módulos (plan 1.4f, D4). Sin permiso: es su propia preferencia.
 */
final class MostrarBarraModulos extends Accion
{
    public function __construct(private readonly NavegacionService $navegacion) {}

    public function clave(): string
    {
        return 'nucleo.mostrar-barra-modulos';
    }

    public function nombre(): string
    {
        return 'Mostrar barra de módulos';
    }

    public function campos(): array
    {
        return [Campo::booleano('visible', 'Mostrar la barra')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->navegacion->mostrarBarra($usuario, $datos['visible']);

        return null;
    }
}
