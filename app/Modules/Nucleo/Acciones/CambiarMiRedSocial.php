<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosRedSocial;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona cambia una de sus redes: la red, el enlace o quién la ve (plan 1.4o, D3). */
final class CambiarMiRedSocial extends AccionMiRedSocial
{
    public function clave(): string
    {
        return 'nucleo.cambiar-mi-red-social';
    }

    public function nombre(): string
    {
        return 'Cambiar mi red social';
    }

    public function campos(): array
    {
        return [Campo::entero('red_id', 'Red')->conReglas('exists:party_redes_sociales,id'), ...self::camposDeDatos(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->redes->cambiar($datos['red_id'], new DatosRedSocial($datos['red_social_id'], $datos['enlace'], $datos['es_privada'], $datos['detalle'] ?? null), $datos['version'], propia: $usuario);

        return null;
    }
}
