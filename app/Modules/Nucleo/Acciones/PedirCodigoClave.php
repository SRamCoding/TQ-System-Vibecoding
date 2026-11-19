<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\ClaveService;

/**
 * Envía a quien la ejecuta el código para cambiar su contraseña, a su correo de acceso (plan 1.4h, D3). Devuelve el
 * correo, en parte oculto. Sin permiso: es su propia cuenta.
 */
final class PedirCodigoClave extends Accion
{
    public function __construct(private readonly ClaveService $claves) {}

    public function clave(): string
    {
        return 'nucleo.pedir-codigo-clave';
    }

    public function nombre(): string
    {
        return 'Pedir el código para cambiar mi contraseña';
    }

    public function campos(): array
    {
        return [];
    }

    /**
     * @return array{correo: string}
     */
    public function ejecutar(array $datos, User $usuario): mixed
    {
        return ['correo' => $this->claves->pedirCodigo($usuario)];
    }
}
