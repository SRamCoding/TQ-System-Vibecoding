<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\NavegacionService;

/**
 * Cada persona elige qué módulos permitidos saca de su barra (plan 1.4f, D4). Se guarda lo oculto, no lo elegido. Sin
 * permiso: es su propia preferencia, y nunca da acceso.
 */
final class ElegirModulos extends Accion
{
    public function __construct(private readonly NavegacionService $navegacion) {}

    public function clave(): string
    {
        return 'nucleo.elegir-modulos';
    }

    public function nombre(): string
    {
        return 'Elegir módulos de la barra';
    }

    public function campos(): array
    {
        return [
            // Reemplaza la lista: vacía muestra todos; omitirla no debe mostrarlos (present).
            Campo::listaDeTextos('ocultos', 'Módulos que no quiero en la barra', 40)->opcional()->conReglas('present'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->navegacion->ocultar($usuario, $datos['ocultos']);

        return null;
    }
}
