<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Estados;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\MotivosService;

/** Agrega un motivo a la lista de una transición (plan 1.5, D9). */
final class AgregarMotivo extends Accion
{
    public function clave(): string
    {
        return 'nucleo.motivos.agregar';
    }

    public function nombre(): string
    {
        return 'Agregar motivo';
    }

    public function campos(): array
    {
        return [
            Campo::entero('tipo_motivo_id', 'Lista de motivos')->conReglas('min:1'),
            Campo::texto('nombre', 'Nombre', maximo: 160),
        ];
    }

    public function permiso(): string
    {
        return 'nucleo.motivos.editar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(MotivosService::class)->agregar($datos['tipo_motivo_id'], $datos['nombre']);
    }
}
