<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona abrió una aplicación desde el panel: suma un uso, que decide las 3 del muelle (plan 1.4p, D5). */
final class RegistrarUsoAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.registrar-uso-aplicacion';
    }

    public function nombre(): string
    {
        return 'Abrir aplicación desde el panel';
    }

    public function campos(): array
    {
        return [Campo::opcion('origen', 'De dónde es', OrigenAplicacion::class), Campo::entero('aplicacion_id', 'Aplicación')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->registrarUso($usuario, $datos['origen'], $datos['aplicacion_id']);

        return null;
    }
}
