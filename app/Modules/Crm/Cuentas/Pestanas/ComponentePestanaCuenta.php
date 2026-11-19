<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Pestanas;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Nucleo\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La base de toda pestaña de la ficha de la cuenta (plan 2.20, D12), como ComponentePestanaFicha. Recibe la cuenta
 * (`$cuentaId`) y revisa en cada petición el permiso de ver cuentas y el de su pestaña. Usa `mount()` o `hydrate()`,
 * nunca `boot()`.
 */
abstract class ComponentePestanaCuenta extends Component
{
    #[Locked]
    public int $cuentaId;

    final public function boot(): void
    {
        $registro = app(RegistroPestanasCuenta::class);
        $pestana = $registro->pestanaPorNombre($this->getName());
        $usuario = auth()->user();
        $usuario = $usuario instanceof User ? $usuario : null;

        abort_unless($pestana !== null && $usuario?->can(CuentasServiceProvider::PERMISO_VER) === true && $registro->puedeVer($usuario, $pestana->permiso), 403);
    }
}
