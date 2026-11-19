<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosRedSocial;

/** La persona agrega una red a su propia ficha (plan 1.4o, D2). Devuelve su id. */
final class AgregarMiRedSocial extends AccionMiRedSocial
{
    public function clave(): string
    {
        return 'nucleo.agregar-mi-red-social';
    }

    public function nombre(): string
    {
        return 'Agregar mi red social';
    }

    public function campos(): array
    {
        return self::camposDeDatos();
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->redes->agregar(self::miParty($usuario), null, new DatosRedSocial($datos['red_social_id'], $datos['enlace'], $datos['es_privada'], $datos['detalle'] ?? null), propia: $usuario);
    }
}
