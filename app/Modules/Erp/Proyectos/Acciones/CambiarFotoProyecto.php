<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Acciones;

use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * El jefe del proyecto sube o cambia su foto de referencia (plan 2.61, D1 y D2). Sin permiso propio: la condición es
 * ser el jefe de ese proyecto, y la revisa `FotoProyectoService`.
 */
final class CambiarFotoProyecto extends Accion
{
    public function __construct(private readonly FotoProyectoService $fotos) {}

    public function clave(): string
    {
        return 'erp.proyectos.cambiar-foto';
    }

    public function nombre(): string
    {
        return 'Cambiar la foto del proyecto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1'),
            Campo::archivo('foto', 'Foto'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->cambiar((int) $datos['proyecto_id'], $datos['foto'], $usuario);

        return null;
    }
}
