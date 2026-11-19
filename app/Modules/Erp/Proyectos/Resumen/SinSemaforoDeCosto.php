<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

use App\Modules\Nucleo\Models\User;

/** Mientras ningún módulo sepa el costo: las tarjetas van sin semáforo. */
final class SinSemaforoDeCosto implements SemaforoDeCosto
{
    public function tonos(array $proyectos, User $usuario): array
    {
        return [];
    }
}
