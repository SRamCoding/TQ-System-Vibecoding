<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;

/**
 * Agrega un elemento a un catálogo. En monedas y tipos de documento fija también lo que mueve dinero,
 * así que exige el permiso de esos campos (E6).
 */
final class CrearElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'crear';
    }

    public function nombre(): string
    {
        return "Agregar a {$this->catalogo->nombre()}";
    }

    public function campos(): array
    {
        return [$this->campoCodigo(), ...$this->catalogo->campos()];
    }

    public function permiso(): string
    {
        return $this->permisoSiMueveDinero();
    }

    /** El id creado: el «+» del selector lo deja elegido (plan 2.0, D2). */
    public function ejecutar(array $datos, User $usuario): int
    {
        $codigo = $datos['codigo'];
        unset($datos['codigo']);

        return $this->edicion->crear($this->catalogo, $codigo, $datos);
    }
}
