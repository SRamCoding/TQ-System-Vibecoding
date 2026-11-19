<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http;

use App\Modules\Nucleo\Filament\Pages\RenovarClave;
use App\Modules\Nucleo\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien debe renovar su contraseña (plan 1.4h, D5) no usa nada más: toda página lo lleva a «Cambia tu contraseña».
 * Es persistente (el proveedor del panel lo declara así): también corre en las peticiones de Livewire.
 * Puede cerrar sesión. De las peticiones de Livewire, solo pasan las de esa pantalla: las de cualquier otro componente
 * se rechazan, aunque alguien las arme a mano.
 */
final class ExigirClaveRenovada
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || ! $usuario->debe_cambiar_clave) {
            return $siguiente($request);
        }

        // Primero Livewire: la ruta sola dejaría pasar cualquier componente dibujado en la página (PeticionLivewire).
        if ($request->hasHeader('X-Livewire')) {
            return PeticionLivewire::soloDe($request, RenovarClave::class) ? $siguiente($request) : abort(403);
        }

        if ($request->routeIs(RenovarClave::getRouteName(), 'filament.app.auth.logout')) {
            return $siguiente($request);
        }

        return redirect()->to(RenovarClave::getUrl());
    }
}
