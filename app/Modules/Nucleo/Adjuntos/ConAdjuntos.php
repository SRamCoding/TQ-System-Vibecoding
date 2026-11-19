<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Adjuntos;

use App\Modules\Nucleo\Models\User;

/**
 * Un registro que puede llevar archivos adjuntos (plan 1.8). Lo implementa el modelo de cada módulo: quién los ve (y
 * los descarga) y quién los agrega o quita. Si además es un documento con estados, `AdjuntosService` respeta su
 * estado: en uno no editable solo se agrega (plan 1.5, D8).
 */
interface ConAdjuntos
{
    public function puedeVerAdjuntos(User $usuario): bool;

    public function puedeAdjuntar(User $usuario): bool;
}
