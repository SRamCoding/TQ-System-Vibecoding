<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona quita una de sus redes (plan 1.4o, D2). */
final class QuitarMiRedSocial extends AccionMiRedSocial
{
    public function clave(): string
    {
        return 'nucleo.quitar-mi-red-social';
    }

    public function nombre(): string
    {
        return 'Quitar mi red social';
    }

    public function campos(): array
    {
        return [Campo::entero('red_id', 'Red')->conReglas('exists:party_redes_sociales,id'), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->redes->quitar($datos['red_id'], $datos['version'], propia: $usuario);

        return null;
    }
}
