<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Pestanas;

use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La base de toda pestaña de la ficha del proyecto (plan 2.10, D3), como ComponentePestanaCuenta. Recibe el proyecto
 * (`$proyectoId`) y revisa en cada petición el permiso de ver proyectos y el de su pestaña. Usa `mount()` o
 * `hydrate()`, nunca `boot()`.
 */
abstract class ComponentePestanaProyecto extends Component
{
    #[Locked]
    public int $proyectoId;

    final public function boot(): void
    {
        $registro = app(RegistroPestanasProyecto::class);
        $pestana = $registro->pestanaPorNombre($this->getName());
        $usuario = auth()->user();
        $usuario = $usuario instanceof User ? $usuario : null;

        abort_unless($pestana !== null && $usuario?->can(ProyectosServiceProvider::PERMISO_VER) === true && $registro->puedeVer($usuario, $pestana->permiso), 403);
    }
}
