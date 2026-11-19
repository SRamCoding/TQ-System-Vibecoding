<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Cambia el valor y, si llega, la descripción de un parámetro editable (M9, plan 1.2b E2). El valor llega
 * como texto y el servicio lo interpreta según el tipo del parámetro; `version` es la que la persona tenía abierta (bloqueo optimista).
 */
final class CambiarParametro extends Accion
{
    public function __construct(private readonly ParametrosService $parametros) {}

    public function clave(): string
    {
        return 'nucleo.cambiar-parametro';
    }

    public function nombre(): string
    {
        return 'Cambiar parámetro';
    }

    public function campos(): array
    {
        return [
            Campo::texto('parametro', 'Parámetro', maximo: 141),
            Campo::texto('valor', 'Valor', maximo: 1000),
            Campo::texto('descripcion', 'Descripción', maximo: 255)->opcional(),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'parametros.editar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parametros->cambiarDesdeTexto($datos['parametro'], $datos['valor'], $datos['version'], $datos['descripcion'] ?? null);

        return null;
    }
}
