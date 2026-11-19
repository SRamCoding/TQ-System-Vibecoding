<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\LogoTechquk;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * Adónde llega la persona al iniciar sesión (plan 1.4g, D4): a la pantalla que eligió, que decide
 * `NavegacionService::pantallaDeInicio()`. Si venía de un enlace concreto (el de una orden que le mandaron), gana el
 * enlace. Reemplaza la respuesta de login de Filament (punto de
 * extensión público). Inicio de sesión es autenticación completa: con MFA, esto corre al completar el último paso.
 * Deja además la marca para que la primera pantalla de adentro anime el logo (plan del logo, D1).
 */
final class RespuestaDeIngreso implements LoginResponse
{
    public function toResponse($request): RedirectResponse
    {
        $usuario = Filament::auth()->user();
        session()->put(LogoTechquk::MARCA_INGRESO, true);
        $destino = $usuario instanceof User ? app(NavegacionService::class)->pantallaDeInicio($usuario) : null;

        return redirect()->intended($destino ?? Filament::getUrl());
    }
}
