<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;

/** Deja de ofrecer un elemento en los selectores; lo ya registrado lo conserva. */
final class DesactivarElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'desactivar';
    }

    public function nombre(): string
    {
        return "Desactivar en {$this->catalogo->nombre()}";
    }

    public function permiso(): string
    {
        return $this->permisoSiMueveDinero();
    }

    public function campos(): array
    {
        return [$this->campoCodigo(), $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->edicion->desactivar($this->catalogo, $datos['codigo'], $datos['version']);

        return null;
    }
}
