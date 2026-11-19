<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Acciones;

use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** El jefe del proyecto quita su foto (plan 2.61, D2); vuelve el dibujo genérico (D12). Sin permiso propio. */
final class QuitarFotoProyecto extends Accion
{
    public function __construct(private readonly FotoProyectoService $fotos) {}

    public function clave(): string
    {
        return 'erp.proyectos.quitar-foto';
    }

    public function nombre(): string
    {
        return 'Quitar la foto del proyecto';
    }

    public function campos(): array
    {
        return [Campo::entero('proyecto_id', 'Proyecto')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->quitar((int) $datos['proyecto_id'], $usuario);

        return null;
    }
}
