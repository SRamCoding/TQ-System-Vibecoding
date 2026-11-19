<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Listeners;

use App\Modules\Nucleo\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Anota cuándo entró la persona por última vez (`users.ultimo_acceso_at`, plan 1.4, D4), para la lista de usuarios.
 * Va directo a la fila: no es un cambio de datos de la cuenta, así que no sube la versión ni el autor.
 */
final class RegistrarUltimoAcceso
{
    public function handle(Login $evento): void
    {
        if ($evento->user instanceof User) {
            DB::table('users')->where('id', $evento->user->getKey())->update(['ultimo_acceso_at' => now()]);
        }
    }
}
