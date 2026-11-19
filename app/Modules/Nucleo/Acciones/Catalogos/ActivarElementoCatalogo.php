<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;

/** Vuelve a ofrecer un elemento desactivado. */
final class ActivarElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'activar';
    }

    public function nombre(): string
    {
        return "Activar en {$this->catalogo->nombre()}";
    }

    public function campos(): array
    {
        return [$this->campoCodigo(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->edicion->activar($this->catalogo, $datos['codigo'], $datos['version']);

        return null;
    }
}
