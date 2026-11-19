<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * La persona suelta una aplicación sobre otra de su misma sección y cambian de lugar en su cuadrícula (plan 1.4z, D10).
 * Solo cambia lo que ve ella.
 */
final class MoverAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.mover-aplicacion';
    }

    public function nombre(): string
    {
        return 'Mover aplicación en tu panel';
    }

    public function campos(): array
    {
        return [
            Campo::opcion('origen', 'De dónde es', OrigenAplicacion::class),
            Campo::entero('aplicacion_id', 'Aplicación')->conReglas('min:1'),
            Campo::entero('sobre_id', 'Sobre cuál')->conReglas('min:1'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->mover($usuario, $datos['origen'], $datos['aplicacion_id'], $datos['sobre_id']);

        return null;
    }
}
