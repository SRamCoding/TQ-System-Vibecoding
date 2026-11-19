<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;

final class OtraAccionConLaMismaClave extends Accion
{
    public function clave(): string
    {
        return 'pruebas.accion';
    }

    public function nombre(): string
    {
        return 'Otra';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return null;
    }
}
