<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Una regla más que el permiso para ejecutar una transición (plan 1.5, D6): un tope de monto, la autoaprobación de
 * la 1.7. Corre dentro del bloqueo, después del permiso, también en las transiciones del sistema.
 */
interface CondicionTransicion
{
    /** Nulo si pasa; si no, el mensaje en español que verá la persona. */
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string;
}
