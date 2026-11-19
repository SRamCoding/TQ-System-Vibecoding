<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Listeners;

use App\Modules\Nucleo\Navegacion\EleccionDeModulos;
use Illuminate\Auth\Events\Login;

/**
 * Con cada inicio de sesión, la ventana «Elegir módulos» vuelve a aparecer la primera vez que se prenda la barra (plan
 * 1.4f, D6). Inicio de sesión es autenticación completa: si un día hay captcha o MFA, esto se mueve al evento del
 * último paso, no al de validar la clave.
 */
final class ReiniciarEleccionDeModulos
{
    public function handle(Login $evento): void
    {
        app(EleccionDeModulos::class)->reiniciar();
    }
}
