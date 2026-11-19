<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ClaveService;

/**
 * Cambia la contraseña de quien la ejecuta con el código que le llegó al correo (plan 1.4h, D3). La regla de la
 * contraseña la revisa el servicio. Sin permiso: es su propia cuenta.
 */
final class CambiarMiClave extends Accion
{
    public function __construct(private readonly ClaveService $claves) {}

    public function clave(): string
    {
        return 'nucleo.cambiar-mi-clave';
    }

    public function nombre(): string
    {
        return 'Cambiar mi contraseña';
    }

    public function campos(): array
    {
        return [
            Campo::texto('codigo', 'Código del correo', maximo: 6),
            Campo::texto('clave', 'Contraseña nueva', maximo: 255),
            Campo::texto('repetida', 'Contraseña nueva, otra vez', maximo: 255),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->claves->cambiarConCodigo($usuario, $datos['codigo'], $datos['clave'], $datos['repetida']);

        return null;
    }
}
