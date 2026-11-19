<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Su ejecutivo o Gerencia Comercial quita la foto (plan 2.21d, D13); vuelve el dibujo de su línea de solución (D1).
 */
final class QuitarFotoOportunidad extends Accion
{
    public function __construct(private readonly FotoOportunidadService $fotos) {}

    public function clave(): string
    {
        return 'crm.oportunidades.quitar-foto';
    }

    public function nombre(): string
    {
        return 'Quitar la foto de la oportunidad';
    }

    public function campos(): array
    {
        return [Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->quitar((int) $datos['oportunidad_id'], $usuario);

        return null;
    }
}
