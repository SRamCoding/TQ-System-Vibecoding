<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Models\User;

/** Cambia los datos de la unidad, también de quién depende (sin formar ciclos). */
final class EditarUnidad extends AccionOrganigrama
{
    protected function operacion(): string
    {
        return 'editar-unidad';
    }

    public function nombre(): string
    {
        return 'Editar unidad';
    }

    public function campos(): array
    {
        return [self::campoUnidad(), ...self::camposDeUnidad(edicion: true), self::campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->organigrama->editarUnidad($datos['unidad_id'], self::datosUnidad($datos), $datos['version']);

        return null;
    }
}
