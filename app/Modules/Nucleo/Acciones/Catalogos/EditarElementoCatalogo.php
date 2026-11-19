<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cambia los campos generales de un elemento; ni el código (E8) ni lo que mueve dinero (E6). */
final class EditarElementoCatalogo extends AccionCatalogo
{
    protected function operacion(): string
    {
        return 'editar';
    }

    public function nombre(): string
    {
        return "Editar en {$this->catalogo->nombre()}";
    }

    public function campos(): array
    {
        $generales = array_values(array_filter(
            $this->catalogo->campos(),
            fn (Campo $campo): bool => ! in_array($campo->nombre, $this->catalogo->camposSensibles(), true),
        ));

        return [$this->campoCodigo(), ...$generales, $this->campoVersion()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $codigo = $datos['codigo'];
        $version = $datos['version'];
        unset($datos['codigo'], $datos['version']);

        $this->edicion->editar($this->catalogo, $codigo, $datos, $version);

        return null;
    }
}
