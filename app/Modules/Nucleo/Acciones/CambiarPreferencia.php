<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Cambia una preferencia de quien la ejecuta, nunca la de otra persona (plan 1.4g, D3). Solo claves registradas y
 * valores que el registro acepta; el valor llega como texto y el registro lo convierte. Sin permiso: es su propia
 * preferencia.
 */
final class CambiarPreferencia extends Accion
{
    public function __construct(private readonly PreferenciasUsuarioService $preferencias) {}

    public function clave(): string
    {
        return 'nucleo.cambiar-preferencia';
    }

    public function nombre(): string
    {
        return 'Cambiar una preferencia';
    }

    public function campos(): array
    {
        return [
            Campo::texto('clave', 'Preferencia', maximo: 80),
            Campo::texto('valor', 'Valor', maximo: 255),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->preferencias->cambiar($usuario, $datos['clave'], $datos['valor']);

        return null;
    }
}
