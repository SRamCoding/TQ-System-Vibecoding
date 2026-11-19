<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Models\User;

/** La persona quita una de sus aplicaciones, con su conteo de uso (plan 1.4p, D7). */
final class QuitarMiAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.quitar-mi-aplicacion';
    }

    public function nombre(): string
    {
        return 'Quitar mi aplicación';
    }

    public function campos(): array
    {
        return [self::campoPropia(), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->quitarPropia($usuario, $datos['aplicacion_id'], $datos['version']);

        return null;
    }
}
