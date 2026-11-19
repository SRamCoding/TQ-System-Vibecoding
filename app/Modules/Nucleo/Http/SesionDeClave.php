<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http;

use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Después de cambiar la contraseña (plan 1.4h, D3 y D5): esta sesión sigue con la nueva; las de otros equipos guardan
 * la vieja y se cierran solas, porque AuthenticateSession compara la de la sesión con la de la cuenta. En una petición
 * de Livewire el middleware guarda su resumen antes de la acción: por eso se actualiza aquí, con su misma llave.
 */
final class SesionDeClave
{
    public static function seguirEnEsteEquipo(User $usuario): void
    {
        $guardia = Filament::auth();
        $hash = $usuario->getAuthPassword();

        session()->put('password_hash_'.Auth::getDefaultDriver(), $guardia instanceof SessionGuard ? $guardia->hashPasswordForCookie($hash) : $hash);
    }
}
