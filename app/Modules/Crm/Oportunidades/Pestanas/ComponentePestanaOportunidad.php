<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Pestanas;

use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La base de toda pestaña de la ficha de la oportunidad (plan 2.21, D12), como ComponentePestanaCuenta. Recibe la
 * oportunidad (`$oportunidadId`) y revisa en cada petición el permiso de ver oportunidades y el de su pestaña. Usa
 * `mount()` o `hydrate()`, nunca `boot()`.
 */
abstract class ComponentePestanaOportunidad extends Component
{
    #[Locked]
    public int $oportunidadId;

    final public function boot(): void
    {
        $registro = app(RegistroPestanasOportunidad::class);
        $pestana = $registro->pestanaPorNombre($this->getName());
        $usuario = auth()->user();
        $usuario = $usuario instanceof User ? $usuario : null;

        abort_unless($pestana !== null && $usuario?->can(OportunidadesServiceProvider::PERMISO_VER) === true && $registro->puedeVer($usuario, $pestana->permiso), 403);
    }
}
