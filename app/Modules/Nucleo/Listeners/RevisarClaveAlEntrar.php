<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Listeners;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ClaveService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Una contraseña guardada está cifrada: solo se puede revisar cuando la persona la escribe (plan 1.4h, D5). Al
 * intentar entrar se anota si cumple la regla (en la petición, nunca la contraseña); al entrar, si no cumplía, la
 * cuenta queda marcada y debe renovarla, y si cumple, la marca se quita. Al restablecerla por correo, también. Inicio de sesión es autenticación completa: con MFA, esto va al último paso.
 */
final class RevisarClaveAlEntrar
{
    private const string ATRIBUTO = 'nucleo.clave_escrita_no_cumple';

    public function alIntentar(Attempting $evento): void
    {
        $clave = $evento->credentials['password'] ?? null;

        if (is_string($clave)) {
            request()->attributes->set(self::ATRIBUTO, ClaveService::faltas($clave) !== []);
        }
    }

    public function alEntrar(Login $evento): void
    {
        $noCumple = request()->attributes->get(self::ATRIBUTO);

        if ($evento->user instanceof User && is_bool($noCumple)) {
            app(ClaveService::class)->revisarAlEntrar($evento->user, ! $noCumple);
        }
    }

    /** «¿Olvidaste tu contraseña?» la restablece con la misma regla: se quita la marca. */
    public function alRestablecer(PasswordReset $evento): void
    {
        if ($evento->user instanceof User) {
            app(ClaveService::class)->alRestablecer($evento->user);
        }
    }
}
