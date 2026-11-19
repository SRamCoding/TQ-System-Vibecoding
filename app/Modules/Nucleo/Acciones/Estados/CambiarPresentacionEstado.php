<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Estados;

use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\EstadosService;

/** Cambia el nombre y el color de un estado de documento (plan 1.5, D1 y D10). Lo demás lo declara el código. */
final class CambiarPresentacionEstado extends Accion
{
    public function clave(): string
    {
        return 'nucleo.estados.cambiar-presentacion';
    }

    public function nombre(): string
    {
        return 'Cambiar nombre y color de un estado';
    }

    public function campos(): array
    {
        return [
            Campo::entero('estado_id', 'Estado')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
            Campo::texto('nombre', 'Nombre', maximo: 80),
            Campo::opcion('color', 'Color', TonoEstado::class),
        ];
    }

    public function permiso(): string
    {
        return 'nucleo.estados.editar-presentacion';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(EstadosService::class)->cambiarPresentacion($datos['estado_id'], $datos['version'], $datos['nombre'], $datos['color']);
    }
}
