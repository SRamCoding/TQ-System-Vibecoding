<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona saca de su panel una aplicación de Techquk que no usa (plan 1.4p, D7). */
final class OcultarAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.ocultar-aplicacion';
    }

    public function nombre(): string
    {
        return 'Ocultar aplicación de Techquk';
    }

    public function campos(): array
    {
        return [Campo::elementoDe('aplicacion_id', 'Aplicación', Catalogo::Aplicaciones)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->ocultar($usuario, $datos['aplicacion_id']);

        return null;
    }
}
