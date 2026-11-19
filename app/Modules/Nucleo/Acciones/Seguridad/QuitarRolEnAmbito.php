<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Termina un rol acotado: deja de valer hoy. Si todavía no empezaba, se borra. */
final class QuitarRolEnAmbito extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'quitar-rol-en-ambito';
    }

    public function nombre(): string
    {
        return 'Quitar rol en un ámbito';
    }

    public function campos(): array
    {
        return [Campo::entero('asignacion_id', 'Asignación')->conReglas('exists:permisos_por_ambito,id'), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->seguridad->quitarRolEnAmbito($datos['asignacion_id'], $datos['version'], $usuario);

        return null;
    }
}
