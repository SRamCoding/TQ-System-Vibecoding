<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Cambia lo que mueve dinero (signo de un documento, decimales de una moneda) con su propio permiso (E6).
 * Solo se registra en los catálogos que tienen esos campos.
 */
final class EditarSensiblesElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'editar-sensibles';
    }

    public function nombre(): string
    {
        return "Editar campos de dinero en {$this->catalogo->nombre()}";
    }

    public function campos(): array
    {
        $sensibles = array_values(array_filter(
            $this->catalogo->campos(),
            fn (Campo $campo): bool => in_array($campo->nombre, $this->catalogo->camposSensibles(), true),
        ));

        return [$this->campoCodigo(), ...$sensibles, $this->campoVersion()];
    }

    public function permiso(): string
    {
        return 'catalogos.editar-sensibles';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $codigo = $datos['codigo'];
        $version = $datos['version'];
        unset($datos['codigo'], $datos['version']);

        $this->edicion->editarSensibles($this->catalogo, $codigo, $datos, $version);

        return null;
    }
}
