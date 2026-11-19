<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Su ejecutivo o Gerencia Comercial sube o cambia la foto de referencia de la oportunidad (plan 2.21d, D2 y D13). Sin
 * permiso propio: la condición la revisa `FotoOportunidadService`.
 */
final class CambiarFotoOportunidad extends Accion
{
    public function __construct(private readonly FotoOportunidadService $fotos) {}

    public function clave(): string
    {
        return 'crm.oportunidades.cambiar-foto';
    }

    public function nombre(): string
    {
        return 'Cambiar la foto de la oportunidad';
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::archivo('foto', 'Foto'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->cambiar((int) $datos['oportunidad_id'], $datos['foto'], $usuario);

        return null;
    }
}
