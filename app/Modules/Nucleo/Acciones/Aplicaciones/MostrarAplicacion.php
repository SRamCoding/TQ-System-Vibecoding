<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona vuelve a poner en su panel una aplicación de Techquk que había ocultado (plan 1.4p, D7). */
final class MostrarAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.mostrar-aplicacion';
    }

    public function nombre(): string
    {
        return 'Mostrar aplicación de Techquk';
    }

    public function campos(): array
    {
        return [Campo::elementoDe('aplicacion_id', 'Aplicación', Catalogo::Aplicaciones)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->mostrar($usuario, $datos['aplicacion_id']);

        return null;
    }
}
