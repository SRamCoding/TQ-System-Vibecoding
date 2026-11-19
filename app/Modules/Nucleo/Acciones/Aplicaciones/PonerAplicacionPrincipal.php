<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AplicacionesService;

/**
 * La persona deja una aplicación de su panel en una de sus 3 principales, las del muelle (plan 1.4x, D2): arrastrándola
 * o con «Poner arriba». Si ya era principal, intercambia lugar con la de esa posición.
 */
final class PonerAplicacionPrincipal extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.poner-aplicacion-principal';
    }

    public function nombre(): string
    {
        return 'Poner aplicación entre tus 3 principales';
    }

    public function campos(): array
    {
        return [
            Campo::entero('posicion', 'Lugar')->conReglas('min:1', 'max:'.AplicacionesService::DEL_MUELLE),
            Campo::opcion('origen', 'De dónde es', OrigenAplicacion::class),
            Campo::entero('aplicacion_id', 'Aplicación')->conReglas('min:1'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->ponerPrincipal($usuario, $datos['posicion'], $datos['origen'], $datos['aplicacion_id']);

        return null;
    }
}
