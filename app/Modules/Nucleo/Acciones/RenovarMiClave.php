<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ClaveService;

/**
 * La renovación obligatoria de la contraseña (plan 1.4h, D5): para quien entró con una que no cumple la regla. Sin
 * código por correo: acaba de entrar con la suya. Sin permiso: es su propia cuenta.
 */
final class RenovarMiClave extends Accion
{
    public function __construct(private readonly ClaveService $claves) {}

    public function clave(): string
    {
        return 'nucleo.renovar-mi-clave';
    }

    public function nombre(): string
    {
        return 'Renovar mi contraseña';
    }

    public function campos(): array
    {
        return [
            Campo::texto('clave', 'Contraseña nueva', maximo: 255),
            Campo::texto('repetida', 'Contraseña nueva, otra vez', maximo: 255),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->claves->renovar($usuario, $datos['clave'], $datos['repetida']);

        return null;
    }
}
