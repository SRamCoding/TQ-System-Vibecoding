<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La base de toda pestaña que otro módulo suma a la ficha (plan 1.4m, D5). Recibe la ficha (`$partyId`) y revisa el
 * permiso de su pestaña en cada petición, no solo al dibujar la ficha. Una pestaña usa `mount()` o `hydrate()`, nunca
 * `boot()`.
 */
abstract class ComponentePestanaFicha extends Component
{
    #[Locked]
    public int $partyId;

    final public function boot(): void
    {
        $registro = app(RegistroPestanasFicha::class);
        $pestana = $registro->porNombre($this->getName());
        $usuario = auth()->user();

        $usuario = $usuario instanceof User ? $usuario : null;

        // También el de ver fichas: una pestaña para todos (permiso nulo) no abre la ficha a quien no la ve.
        abort_unless($pestana !== null && $usuario?->can('parties.ver') === true && $registro->puedeVer($usuario, $pestana), 403);
    }
}
