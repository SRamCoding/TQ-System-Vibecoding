<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http;

use App\Modules\Nucleo\Filament\Pages\FaltaGerenteGeneral;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin gerente general activo el sistema no arranca (plan 1.10, D2): las alertas de control no tendrían a quién llegar.
 * Toda página lleva a «Falta registrar al gerente general» y, de Livewire, solo pasan las peticiones de esa pantalla.
 * Se puede cerrar sesión. Quien debe renovar su contraseña lo hace antes (ExigirClaveRenovada ya lo encierra ahí).
 * Persistente, como aquel. Solo las pruebas lo apagan (`nucleo.exigir_gerente_general`).
 */
final readonly class ExigirGerenteGeneral
{
    public function __construct(private SeguridadService $seguridad) {}

    public function handle(Request $request, Closure $siguiente): Response
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || $usuario->debe_cambiar_clave || ! config('nucleo.exigir_gerente_general') || $this->seguridad->hayGerenteGeneralActivo()) {
            return $siguiente($request);
        }

        if ($request->hasHeader('X-Livewire')) {
            return PeticionLivewire::soloDe($request, FaltaGerenteGeneral::class) ? $siguiente($request) : abort(403);
        }

        if ($request->routeIs(FaltaGerenteGeneral::getRouteName(), 'filament.app.auth.logout')) {
            return $siguiente($request);
        }

        return redirect()->to(FaltaGerenteGeneral::getUrl());
    }
}
