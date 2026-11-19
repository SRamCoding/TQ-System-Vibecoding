<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;

/** Borra un elemento que nunca se usó; si ya se usó, se rechaza y se sugiere desactivarlo (E1). */
final class BorrarElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'borrar';
    }

    public function nombre(): string
    {
        return "Borrar de {$this->catalogo->nombre()}";
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
        $this->edicion->borrar($this->catalogo, $datos['codigo'], $datos['version']);

        return null;
    }
}
